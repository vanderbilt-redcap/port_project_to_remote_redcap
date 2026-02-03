Feature: Global Configuration Setting 1: The system shall support setting Warning Text when enabling the module.
         As a REDCap end user I want to see that the Warning Text shows.

      Scenario: Global Configuration Setting 1: The system shall support setting Warning Text when enabling the module.
   #Active module in the control center and set the warning text.
      Given I login to REDCap with the user "Test_Admin"
      And I click on the link labeled "Control Center"
      And I click on the link labeled "Manage"
      Then I should see "External Modules - Module Manager"
      When I click on the button labeled "Enable a module"
      Then I should see "Port Project to Remote REDCap"
      When I click on the second button labeled "Enable"
      Then I should see "Enable Module:"
      When I click on the button labeled "Enable"
      Then I should see "Port Project to Remote REDCap"
      When I click on the button labeled "Configure"
      Then I should see "Configure Module:"
      When I enter "This is the port project warning text when on the module page" into the field labeled "Warning Text:"
      And I click on the button labeled "Save"
      Then I should see "Modules Currently Available on this System"

   #SETUP Create Source Project with Module enabled
      When I click on the link labeled "My Projects"
      And I create a new project named " EM Port Project to Remote REDCap Test 1 - Source" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "Project_1.xml", and clicking the "Create Project" button

   #SETUP Turning on EM at the source project
      When I click on the link labeled "My Projects"
      And I click on the link labeled "EM Port Project to Remote REDCap Test 1 - Source"
      And I click on the link labeled "Project Setup"
      Then I should see "External Modules"
      When I click on the link labeled "Manage"
      Then I should see "External Modules - Project Module Manager"
      When I click on the button labeled "Enable a module"
      Then I should see "Available Modules"
      When I click on the button labeled "Enable"
      Then I should see "Port Project to Remote REDCap"
      When I click on the link labeled "Copy Project to Remote REDCap"
      Then I should see "This is the port project warning text when on the module page"

   #VERIFY log Enabling and configuring the port project module.
      When I click on the link labeled "Logging"
      Then I should see a table header and rows containing the following values in the logging table:
         | Time / Date      | Username   | Action                                                                                     | List of Data Changes OR Fields Exported |
         | mm/dd/yyyy hh:mm | test_admin | Enable external module "port_project_to_remote_redcap_v9.9.9" for project                  |   |
      
#END