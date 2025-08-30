<?php

call_user_func(function () {
    /**
     * Automatically add fixture extensions to the `typo3/testing-framework`
     * {@see \TYPO3\TestingFramework\Composer\ComposerPackageManager} to
     * allow composer package name or extension keys of fixture extension in
     * {@see \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase::$testExtensionToLoad}.
     */
    if (class_exists(\SBUERK\AvailableFixturePackages::class)) {
        (new \SBUERK\AvailableFixturePackages())->adoptFixtureExtensions();
    }

    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
});
