'use strict';

describe('Service: restHandler', function () {

  // load the service's module
  beforeEach(module('webApp'));

  // instantiate service
  var restHandler;
  beforeEach(inject(function (_restHandler_) {
    restHandler = _restHandler_;
  }));

  it('should do something', function () {
    //expect(restHandler.query('trace-user','user1=tonydspaniard&user2=GromNaN')).toBe(true);
  });

});
