'use strict';


angular.module('webApp')
.controller('FindCtrl', function ($scope,findService) {
  $scope.errors = {};

  $scope.find = function(){
    $scope.$broadcast('show-errors-check-validity');

    if ($scope.findForm.$valid) {

      $scope.errors.connectiondown = false;
      $scope.errors.invalidpackage = false;

      $scope.processing = true;
      $scope.users = [];

      findService.trace($scope.pack).success(function(data){
        console.log(data);
        if(!data.data.contributors_found){
          $scope.errors.invalidpackage = true;
          $scope.processing = false;
          return;
        }

        $scope.users = data.data.contributors;

        $scope.processing = false;
      }).
      error(function(data){
        $scope.errors.connectiondown = true;
        $scope.errors.invalidpackage = false;
        $scope.processing = false;
      });
    }
  };

  var init = function(){
    $scope.processing = false;
  };
  init();

});
