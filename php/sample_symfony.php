<?php

namespace AppBundle\Services\Tactic;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Tactic;
use AppBundle\Entity\TacticCost;
use AppBundle\Entity\TacticMolecule;
use AppBundle\Entity\TacticType;
use AppBundle\Entity\MedicalPlan;
use AppBundle\Entity\MedicalGap;
use AppBundle\Entity\TacticStakeholder;

/**
 * Class TacticUpdateService
 * @package AppBundle\Services\Tactic
 */
class TacticUpdateService
{
  /**
   * @var EntityManager
   */
  private $entityManager;

  /**
   * @var Tactic
   */
  public $tactic = null;

  /**
   * @var TacticType
   */
  public $tacticType = null;

  /**
   * @var array
   */
  public $tacticParams = null;

  /**
   * @param EntityManager $entityManager
   */
  public function __construct(EntityManager $entityManager)
  {
    $this->entityManager = $entityManager;
  }

  /**
   * @param MedicalPlan $medicalPlan
   * @param $tacticParams
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Exception
   * @return Tactic
   */
  public function addTactic(MedicalPlan $medicalPlan, $tacticParams)
  {
    $this->tacticParams = $tacticParams;
    /** @var Tactic $tactic */
    $this->tactic = new Tactic();
    $this->tacticType = $this->entityManager->getRepository('HiqAppBundle:TacticType')
      ->findOneById(intval($this->tacticParams['tacticType']));
    $this->mapTacticParamsToTacticObject($medicalPlan);
    $this->doAddTransaction();

    return $this->tactic;
  }

  /**
   * @param MedicalPlan $medicalPlan
   * @param $tactic
   * @param $tacticParams
   * @return Tactic
   */
  public function editTactic(MedicalPlan $medicalPlan, $tactic, $tacticParams)
  {
    $this->tacticParams = $tacticParams;
    $this->tactic = $tactic;
    $currentTacticType = $this->tactic->getTacticType();

    $this->tacticType = $this->entityManager->getRepository('HiqAppBundle:TacticType')
      ->findOneById(intval($this->tacticParams['tacticType']));
    $this->maptacticParamsToTacticObject($medicalPlan);
    $tacticTypeChanged = false;
    if ($currentTacticType->getId() != $this->tacticType->getId()) {
      $tacticTypeChanged = true;
    }
    $this->doEditTransaction($tacticTypeChanged);

  }

  /**
   * Method to insert new Tactic,Tactic costs, Tactic Molecules, Tactic Stakeholders
   * @throws \Exception
   * @return boolean
   */
  public function doAddTransaction()
  {
    $tactic = $this->tactic;
    $that = $this;
    $response = $this->entityManager->transactional(
      function (EntityManager $em) use ($tactic, $that) {
        if (isset($that->tacticParams['costDetails']) && !empty($that->tacticParams['costDetails'])) {
          $budgetYearCost = $that->persistTacticCostsGetBudgetYearCost();
        }
        $tactic->setTotalBudgetYearCost(isset($budgetYearCost) ? $budgetYearCost : 0);
        $tactic->setTeamPriority(0);
        $that->updateTacticStakeHolders();
        $that->updateTacticMolecules();
        $em->persist($tactic);
        $em->flush();
      }
    );

    return $response;
  }

  /**
   * Method to update tactics
   * 1) if tactic type has changed , remove all existing tactic cost details and insert the new ones
   * 2) if tactic type has no data in tactic type cost center category
   *      a) Do not insert the ones with zero values for cost
   *      b) Consider the ones with non zero values in tacticCost table and zero values from param as the one to be removed
   *      c) Update the ones which has non zero values for cost and which already exists
   *      d) Insert the ones which has non zero values for cost and which doen't exist in tactic cost table
   * 3) if tactic type has data in tactic type cost center category update the costs
   * 4) update tactic molecules and tactic stakeholders
   *
   * @param $tacticTypeChanged
   * @throws \Exception
   * @return boolean
   */
  public function doEditTransaction($tacticTypeChanged)
  {
    $tactic = $this->tactic;
    $that = $this;
    $response = $this->entityManager->transactional(
      function (EntityManager $em) use ($tacticTypeChanged, $tactic, $that) {

        if ($tacticTypeChanged) {
          foreach ($tactic->getTacticCosts() as $tacticCost) {
            $em->remove($tacticCost);
            $tactic->removeTacticCost($tacticCost);
          }
        }
        $budgetYearCost = 0;
        if (isset($that->tacticParams['costDetails']) && !empty($that->tacticParams['costDetails'])) {
          $budgetYearCost = $that->persistTacticCostsGetBudgetYearCost();
        }

        $tactic->setTotalBudgetYearCost($budgetYearCost);
        $tactic->isInBudgetYear();
        if ($budgetYearCost == 0 || !$tactic->isInBudgetYear()) {
          $tactic->setTeamPriority(0);
        }

        $that->updateTacticStakeHolders();
        $that->updateTacticMolecules();
        $em->flush();
      }
    );

    return $response;
  }

  /**
   * @param $costData
   * @return
   */
  public function roundCostValues($costData)
  {
    $costData['spentCost'] = ceil(floatval($costData['spentCost']));
    $costData['budgetYearCost'] = ceil(floatval($costData['budgetYearCost']));
    $costData['year2Cost'] = ceil(floatval($costData['year2Cost']));
    $costData['year3Cost'] = ceil(floatval($costData['year3Cost']));
    $costData['afterYear3Cost'] = ceil(floatval($costData['afterYear3Cost']));

    return $costData;
  }

  /**
   * Method to insert tactic costs to tactic cost table and update budget year cost for the tactic
   * If tactic type cost center category has no data and cost has zero values do not insert
   * Update the tactic cost which comes with an id
   * @return int
   */

  public function persistTacticCostsGetBudgetYearCost()
  {
    $budgetYearCost = 0;
    foreach ($this->tacticParams['costDetails'] as $costData) {

      $costData = $this->roundCostValues($costData);

      /** @var TacticCost $tacticCost */
      $tacticCost = null;
      if (isset($costData['id'])) {
        $tacticCost = $this->entityManager->getRepository('HiqAppBundle:TacticCost')
          ->findOneBy(array('id' => intval($costData['id']), 'tactic' => $this->tactic));
        if ((!isset($costData['ccCategoryId']) || is_null($costData['ccCategoryId']))
          && !$this->tactic->isTacticTypeMedicalUnitCCCategoryRelationExists()
          && !is_null($tacticCost)
        ) {
          $this->entityManager->remove($tacticCost);
          continue;
        }
      }
      // on add or update add new tactic costs if it is a new item
      if (is_null($tacticCost)) {
        $tacticCost = new TacticCost();
      }
      $res = $this->mapTacticParamsToTacticCostObject($tacticCost, $costData);
      if ($res) {
        $budgetYearCost += $costData['budgetYearCost'];
        $this->tactic->addTacticCost($tacticCost);
        $this->entityManager->persist($tacticCost);
      }
    }
    return $budgetYearCost;

  }


  /**
   * Method to update tactic StakeHolders
   * 1)Remove tactic StakeHolders which is already in tactic Molecule table and not in new list of stakeHolders params
   * 2) Skip the StakeHolders which are already there
   * 3) insert the  StakeHolders to tactic stakeholder table if it doesn't exist in tactic molecule table
   */
  public function updateTacticStakeHolders()
  {
    if (isset($this->tacticParams['stakeholders'])) {

      if (!$this->tactic->getTacticStakeholders()->isEmpty()) {

        $newStakeholderIds = $this->getAllNewlyAddedIdFromParams($this->tacticParams['stakeholders']);
        $toSkip = array();
        foreach ($this->tactic->getTacticStakeholders() as $tacticStakeholder) {
          if (in_array($tacticStakeholder->getStakeholder()->getId(), $newStakeholderIds)) {
            $toSkip[] = $tacticStakeholder->getStakeholder()->getId();
          } else {
            $this->entityManager->remove($tacticStakeholder);
          }
        }

        $this->saveNewIdsFromParams($toSkip, $newStakeholderIds, "persistTacticStakeholder");
      } else {
        foreach ($this->tacticParams['stakeholders'] as $skh) {
          $this->persistTacticStakeholder($skh['id']);
        }
      }
    }

  }


  /**
   * Method to update tactic molecules
   * 1)Remove tactic molecules which is already in tactic Molecule table and not in new list of molecule params
   * 2) Skip the molecules which are already there
   * 3) Insert the molecules which doesn't exist in tactic molecule table
   */
  public function updateTacticMolecules()
  {
    if (isset($this->tacticParams['molecules'])) {

      if (!$this->tactic->getTacticMolecules()->isEmpty()) {
        $toSkip = array();
        $newMoleculeIds = $this->getAllNewlyAddedIdFromParams($this->tacticParams['molecules']);

        foreach ($this->tactic->getTacticMolecules() as $tacticMolecule) {
          if (in_array($tacticMolecule->getMolecule()->getId(), $newMoleculeIds)) {
            $toSkip[] = $tacticMolecule->getMolecule()->getId();
          } else {
            $this->entityManager->remove($tacticMolecule);;
          }
        }
        $this->saveNewIdsFromParams($toSkip, $newMoleculeIds, "persistTacticMolecule");
      } else {
        foreach ($this->tacticParams['molecules'] as $mol) {
          $this->persistTacticMolecule($mol['id']);
        }
      }
    }
  }


  public function getAllNewlyAddedIdFromParams($paramArray)
  {
    $newlyAddedIds = array_map(
      function ($data) {
        return $data['id'];
      },
      $paramArray
    );

    return $newlyAddedIds;
  }

  public function persistTacticMolecule($moleculeId)
  {
    $molecule = $this->entityManager->getRepository('HiqAppBundle:Molecule')
      ->findOneById(intval($moleculeId));
    /** @var TacticMolecule $tacticMolecule */
    if (!is_null($molecule)) {
      $tacticMolecule = new TacticMolecule();
      $tacticMolecule->setTactic($this->tactic)->setMolecule($molecule);
      $this->entityManager->persist($tacticMolecule);
    }
  }

  public function persistTacticStakeholder($stakeholderId)
  {
    $stakeholder = $this->entityManager->getRepository('HiqAppBundle:Stakeholder')
      ->findOneById(intval($stakeholderId));
    if (!is_null($stakeholder)) {
      $tacticStakeholder = new TacticStakeholder();
      $tacticStakeholder->setTactic($this->tactic)->setStakeholder($stakeholder);
      $this->entityManager->persist($tacticStakeholder);
    }
  }


  /**
   * Method to map form parameters to Tactic object
   * @param $medicalPlan
   * @return Tactic
   */
  public function mapTacticParamsToTacticObject($medicalPlan)
  {
    $medicalGap = $indication = $purpose = null;
    if (isset($this->tacticParams['medicalGap'])) {
      $medicalGap = $this->entityManager->getRepository('HiqAppBundle:MedicalGap')
        ->findOneBy(
          array(
            'id' => intval($this->tacticParams['medicalGap']),
            'medicalPlan' => $medicalPlan
          )
        );
    }
    if (isset($this->tacticParams['indication'])) {
      $indication = $this->entityManager->getRepository('HiqAppBundle:Indication')
        ->findOneById(intval($this->tacticParams['indication']));
    }
    if (isset($this->tacticParams['tacticPurpose'])) {
      $purpose = $this->entityManager->getRepository('HiqAppBundle:TacticPurpose')
        ->findOneById(intval($this->tacticParams['tacticPurpose']));
    }
    $date = array('startDate', 'endDate', 'gatedInDate', 'gatedOutDate');
    $excludeList = array(
      'tacticType',
      'indication',
      'medicalGap',
      'costDetails',
      'id',
      'stakeholders',
      'tacticPurpose',
      'molecules'
    );
    $this->tactic->setMedicalPlan($medicalPlan);
    foreach ($this->tacticParams as $key => $value) {

      if (in_array($key, $excludeList)) {
        continue;
      }
      if (in_array($key, $date) && $value != null) {
        $value = new \DateTime($value);
      }
      $method = 'set' . ucfirst($key);
      $this->tactic->$method($value);
    }

    /** @var MedicalGap $medicalGap */
    $this->tactic
      ->setTacticType($this->tacticType)
      ->setMedicalGap($medicalGap)
      ->setIndication($indication)
      ->setTacticPurpose($purpose)
      ->setIsFunded(false);

    return $this->tactic;
  }

  /**
   * Method to map Cost details to Tactic Cost Object
   * @param $tacticCost
   * @param $costData
   * @return bool
   */
  public function mapTacticParamsToTacticCostObject(&$tacticCost, $costData)
  {
    if (isset($costData['ccCategoryId']) && !is_null($costData['ccCategoryId'])) {
      $costCenterCategory = $this->entityManager->getRepository('HiqAppBundle:CostCenterCategory')
        ->findOneById(intval($costData['ccCategoryId']));
      /** @var TacticCost $tacticCost */
      if (!is_null($costCenterCategory)) {
        $tacticCost->setCostCenterCategory($costCenterCategory)
          ->setTactic($this->tactic)
          ->setSpentCost($costData['spentCost'])
          ->setBudgetYearCost($costData['budgetYearCost'])
          ->setYear2Cost($costData['year2Cost'])
          ->setYear3Cost($costData['year3Cost'])
          ->setAfterYear3Cost($costData['afterYear3Cost']);

        return true;
      }
    }

    return false;
  }

  /**
   * @param Tactic $tactic
   * @throws \Exception
   */
  public function deleteTactic(Tactic $tactic)
  {
    $this->entityManager->transactional(
      function (EntityManager $em) use ($tactic) {
        foreach ($tactic->getTacticCosts() as $tacticCost) {
          $em->remove($tacticCost);
        }
        foreach ($tactic->getTacticMolecules() as $tacticMolecule) {
          $em->remove($tacticMolecule);
        }
        foreach ($tactic->getTacticStakeholders() as $tacticStakeholder) {
          $em->remove($tacticStakeholder);
        }
        $em->remove($tactic);
        $em->flush();
      }
    );
  }

  /**
   * @param $toSkip
   * @param $newIds
   * @param $type
   */
  public function saveNewIdsFromParams($toSkip, $newIds, $type)
  {
    if (count($toSkip) != count($newIds)) {
      foreach ($newIds as $id) {
        if (!in_array($id, $toSkip)) {
          $this->$type($id);
        }
      }
    }
  }

}