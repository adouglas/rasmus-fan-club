'use strict';

/**
 * @ngdoc service
 * @name webApp.traceService
 * @description
 * # traceService
 * Service in the webApp.
 */
angular.module('webApp')
  .service('traceService', function (restHandler) {
    var resource = 'trace-user';
    this.trace = function(start,end){
      return restHandler.query(resource,'user1=' + start + '&user2=' + end);
    }
  });
