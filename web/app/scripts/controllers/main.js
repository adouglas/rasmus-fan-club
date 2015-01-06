'use strict';

/**
 * @ngdoc function
 * @name webApp.controller:MainCtrl
 * @description
 * # MainCtrl
 * Controller of the webApp
 */
angular.module('webApp')
  .controller('MainCtrl', function ($scope, $location, $anchorScroll) {
    $scope.scrollDown = function(anchorID){
      $location.hash(anchorID);

      // call $anchorScroll()
      $anchorScroll();
    };

    var init = function(){
      // You would ususaly store this and track clicks
      var abRand = Math.floor(Math.random() * 3);
  
      $scope.abtest = {
        mainbutton: abRand
      };
    };

    init();
  });
