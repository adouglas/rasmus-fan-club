'use strict';

/**
 * @ngdoc service
 * @name webApp.findService
 * @description
 * # findService
 * Service in the webApp.
 */
angular.module('webApp')
  .service('findService', function (restHandler) {
    var resource = 'find-contributors';
    this.trace = function(pack){
      return restHandler.query(resource,'package=' + pack);
    };
  });
