'use strict';

/**
 * @ngdoc service
 * @name webApp.restHandler
 * @description
 * # restHandler
 * Service in the webApp.
 */
angular.module('webApp')
  .service('restHandler', function ($http) {
    // AngularJS will instantiate a singleton by calling "new" on this function
    //var baseURL = 'http://api.recruitmate.greydog.co';
    var baseURL = 'http://api.rasmus.freya.local';

    this.query = function(resource,queryParams){
      return $http.get(baseURL + '/' + resource + '?' + queryParams);
    }
  });
