<?php

/**
 * Class promo_engine_actions_AddAppItem
 * @property string  currentAppSku$sku
 * @property int     $quantity
 * @property int     $percent
 */
class promo_engine_actions_AddAppItem extends promo_engine_actions_PercentDiscount
{
  const ADD_SKU = 'add_to_cart';
  private $currentAppSku = null;

  /**
   * Execute the actions
   * @return mixed
   */
  function run()
  {
    $shopHelper = shop_ShopObjectHelper::instance($this->factory->getHelper()->getCountry());

    if ($this->cart->hasAppSku()) {
      $this->currentAppSku = $this->cart->getAppSku();
      $this->cart->updateInfo($this->currentAppSku, array());
    }
    $this->cart->add($this->sku, 1, 1, $shopHelper->getAppSkuInfo($this->sku));
    $this->persistCart($shopHelper);
  }


  /**
   * @param shop_estimator_OrderDetailsTransformerControlsInterface|shop_OrderDetailsEstimatorInterface $estimator
   */
  public function estimate($estimator)
  {
    $lineCollection = new data_collections_shop_LineItems();
    $lines = $estimator->getLines();

    $newLine = data_object_shop_LineItem::fromSkuAndQuantity($this->cart,$this->sku,1);
    $discount = $this->getDiscount();

    if(!empty($discount))
    {
      $newLine->addDiscount($discount);
      $newLine->{data_object_shop_LineItem::KEY_OFFER_CODE} = $this->promoObject->promo_code;
    }
    $lineCollection->addLines($newLine);

    foreach($lines as $l)
    {
      if($l->{data_object_shop_LineItem::KEY_PARENT_SKU} == $this->currentAppSku)
      {
        $l->{data_object_shop_LineItem::KEY_INFO} = array();
      }
      if($l->{data_object_shop_LineItem::KEY_PARENT_SKU} != $this->sku )
      {
        $lineCollection->addLines($l);
      }

    }
    $estimator->setLines($lineCollection);
  }


  /**
   * @param $shopHelpers
   */
  private function persistCart($shopHelper)
  {
    $context =$this->factory->getContext();
    if($this->factory->getDataRequestProcessor()->client == data_enum_SubSystemsEnum::KEY_MEMBER_CENTER){
      shop_ShopHelper::saveCart($this->cart,$context, $shopHelper,'sponsor');
    }else{
      shop_ShopHelper::saveCart($this->cart,$context, $shopHelper);
    }
  }
}