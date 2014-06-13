'use strict';

module.exports = function ($scope, $q, StatisticsService, FixityService, AIPService) {

  var pull = function () {

    var queries = [
      // StatisticsService.getArtworkByMonthSummary(),
      StatisticsService.getDownloadActivity(),
      StatisticsService.getIngestionActivity(),
      StatisticsService.getIngestionSummary(),
      StatisticsService.getRunningTotalByDepartment(),
      StatisticsService.getRunningTotalByCodec(),
      StatisticsService.getRunningTotalByFormat(),
      StatisticsService.getArtworkSizesByYearSummary(),
      StatisticsService.getArtworkCountsAndTotalsByDate(),
      AIPService.getUuidsOfAipsMatchingStatus('RECOVER_REQ')
    ];

    $q.all(queries).then(function (responses) {
      $scope.downloadActivity = responses[0].data.results;
      $scope.ingestionActivity = responses[1].data.results;
      $scope.ingestionSummary = {
        accessKey: 'total',
        formatKey: 'type',
        data: responses[2].data.results
      };
      $scope.countByDepartment = {
        accessKey: 'count',
        formatKey: 'department',
        data: responses[3].data.results
      };
      $scope.storageCodecs = {
        accessKey: 'total',
        formatKey: 'codec',
        data: responses[4].data.results
      };
      $scope.storageFormats = {
        accessKey: 'total',
        formatKey: 'media_type',
        data: responses[5].data.results
      };
      $scope.artworkSizes = [{
        name: 'Average',
        color: 'steelblue',
        xProperty: 'year',
        yProperty: 'average',
        data: responses[6].data.results
      }];
      $scope.yearlyCountsByCollectionDate = [{
        name: 'Year',
        color: 'hotpink',
        xProperty: 'year',
        yProperty: 'count',
        data: responses[7].data.results.collection
      }];
      $scope.monthlyCountsByCreation = [{
        name: 'Month',
        color: 'hotpink',
        xProperty: 'month',
        xLabelFormat: 'yearAndMonth',
        yProperty: 'count',
        data: responses[7].data.results.creation
      }];
      $scope.yearlyTotalsByCollectionDate = [{
        name: 'Year',
        color: 'hotpink',
        xProperty: 'year',
        yProperty: 'total',
        data: responses[7].data.results.collection
      }];
      $scope.monthlyTotalsByCreation = [{
        name: 'Month',
        color: 'hotpink',
        xProperty: 'month',
        xLabelFormat: 'yearAndMonth',
        yProperty: 'total',
        data: responses[7].data.results.creation
      }];
      $scope.aipsPendingRecovery = responses[8].data.uuids;
    });

  };

  pull();

  $scope.showOverview = false;
  $scope.toggleOverview = function () {
    $scope.showOverview = !$scope.showOverview;
  };

  $scope.fixityHasFails = false;
  FixityService.getStatusFixity({ limit: 5 }).then(function (response) {
    $scope.fixityStats = response.data;
    if ($scope.fixityStats.hasOwnProperty('lastFails') && $scope.fixityStats.lastFails.length > 0) {
      $scope.fixityHasFails = true;
      $scope.showOverview = true;
    }
    // Convert boolean to human-friendly string
    angular.forEach($scope.fixityStats.lastChecks, function (e) {
      if (e.outcome === false) {
        e.statusAlert = 'Failed';
      } else if (e.outcome === true) {
        e.statusAlert = 'Success';
      } else {
        return;
      }
    });
  });

  // Set visibility of fixity details to false by default
  // If failed fixity checks exist, this value will be set
  // to true in then() following service call
  $scope.showOverview = false;
  $scope.toggleOverview = function () {
    $scope.showOverview = !$scope.showOverview;
  };

  // Check if AIP is pending recovery
  $scope.isPendingRecovery = function (uuid) {
    if (
      typeof uuid === 'undefined' || typeof $scope.aipsPendingRecovery === 'undefined'
    ) {
      return false;
    }

    return $scope.aipsPendingRecovery.indexOf(uuid) !== -1;
  };

  // Allow request for AIP recovery
  $scope.requestRecover = function (uuid) {
    AIPService.recoverAip(uuid)
      .success(function () {
        pull();
      })
      .error(function () {
        console.log('Error requesting AIP recovery');
      });
  };
};
