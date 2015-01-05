'use strict';

describe('Service: findService', function () {

  // load the service's module
  beforeEach(module('webApp'));

  // instantiate service
  var findService;
  beforeEach(inject(function (_findService_) {
    findService = _findService_;
  }));

  it('should do something', function () {
    expect(!!findService).toBe(true);
  });

});
