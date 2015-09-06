app.controller('meetupsController',['$scope','$resource', function($scope,$resource){
    var Meetup = $resource('api/meetups');
    $scope.meetups = [
        {"name":"SF Developers"},
        {"name":"Fremont Developers"}
    ]
    $scope.createMeetup = function(){
        var meetup = new Meetup();
        meetup.name = $scope.meetupName;
        meetup.$save();
    }
}]);
