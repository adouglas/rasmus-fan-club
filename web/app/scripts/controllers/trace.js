'use strict';


angular.module('webApp')
  .controller('TraceCtrl', function ($scope,$location,traceService) {
    $scope.users = {};
    $scope.errors = {};

    $scope.trace = function(){
      $scope.$broadcast('show-errors-check-validity');

      if (($scope.traceForm && $scope.traceForm.$valid) || !$scope.traceForm) {

        $scope.errors.connectiondown = false;
        $scope.errors.invalidusers = false;

        $scope.processing = true;
        $scope.paths = [];

        $location.search({user1: $scope.users.user1, user2: $scope.users.user2});

        traceService.trace($scope.users.user1,$scope.users.user2).success(function(data){
          if(!data.data.path_found){
            $scope.errors.invalidusers = true;
            $scope.processing = false;
            return;
          }

          $scope.paths = data.data.paths;

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
      
      var searchObject = $location.search();
      $scope.$on('$viewContentLoaded', function(){
        if(searchObject && searchObject.user1 && searchObject.user2){
          $scope.users.user1 = searchObject.user1;
          $scope.users.user2 = searchObject.user2;
          $scope.trace();
        }
      });

    };
    init();

  });
