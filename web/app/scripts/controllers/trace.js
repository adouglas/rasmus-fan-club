'use strict';


angular.module('webApp')
  .controller('TraceCtrl', function ($scope,traceService) {
    $scope.users = {};
    $scope.errors = {};

    $scope.trace = function(){
      $scope.$broadcast('show-errors-check-validity');

      if ($scope.traceForm.$valid) {

        $scope.errors.connectiondown = false;
        $scope.errors.invalidusers = false;

        $scope.processing = true;
        $scope.paths = [];

        traceService.trace($scope.users.user1,$scope.users.user2).success(function(data){
          if(!data.data.path_found){
            $scope.errors.invalidusers = true;
            $scope.processing = false;
            return;
          }

          $scope.paths = data.data.paths;

          console.log(data);
          $scope.processing = false;
        }).
        error(function(data){
          $scope.errors.connectiondown = true;
          $scope.errors.invalidusers = false;
          $scope.processing = false;
        });
      }
    };

    var init = function(){
      $scope.processing = false;
    };
    init();

  });
