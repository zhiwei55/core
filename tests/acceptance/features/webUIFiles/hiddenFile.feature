@webUI @insulated @disablePreviews
Feature: Hide file/folders

  As a user
  I would like to display hidden files/folders
  So that I can choose to see the files that I want.

  Background:
    Given user "user1" has been created
    And the user has browsed to the login page
    And the user has logged in with username "user1" and password "%alt1%" using the webUI
    And the user has browsed to the files page

  @smokeTest
  Scenario: create a hidden folder
    When the user creates a folder with the name ".xyz" using the webUI
    Then the folder ".xyz" should not be listed on the webUI
    When the user enables the setting to view hidden folders on the webUI
    Then the folder ".xyz" should be listed on the webUI
