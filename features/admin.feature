Feature: Admin system overview
  In order to monitor the platform
  As an admin
  I need to view system-wide statistics

  Scenario: Admin can view the system overview page
    Given I am logged in as admin
    When I am on "/admin-stats"
    Then I should see "System Overview"
    And I should see "Companies"
    And I should see "Users"
