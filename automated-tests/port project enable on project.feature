Feature: EM Port Project to Remote REDCap Test 1: The system shall support enabling the port project external module on a REDCap project.
         As a REDCap end user I want to see that the port project external module can be turned on for a project.

      Scenario: EM Port Project to Remote REDCap Test 1: The system shall support enabling the port project external module on a REDCap project.
   #Active module in the control center.
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

   #SETUP Create Source Project with Module enabled
      When I click on the link labeled "My Projects"
      And I create a new project named " EM Port Project to Remote REDCap Test 1 - Source" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "Project_1.xml", and clicking the "Create Project" button

   #SETUP Create Destination Project
      When I click on the link labeled "My Projects"
      And I click on the link labeled "New Project"
      And I enter "EM Port Project to Remote REDCap Test 1 - Destination" into the field labeled "Project title" 
      And I select "Practice / Just for fun" on the dropdown field labeled "Project's purpose:"
      And I click on the button labeled "Create Project"
      Then I should see "EM Port Project to Remote REDCap Test 1 - Destination"

   #SETUP Turning on EM at the source project
      When I click on the link labeled "My Projects"
      And I click on the link labeled "EM Port Project to Remote REDCap Test 1 - Source"
      And I click on the link labeled "Project Setup"
      Then I should see "External Modules"
      When I click on the link labeled "Manage"
      Then I should see "External Modules - Project Module Manager"
      When I click on the button labeled "Enable a module"
      Then I should see "Available Modules"
      #When I click on the button labeled "Enable" in the action popup
      When I click on the button labeled "Enable"
      Then I should see "Port Project to Remote REDCap"
      
   #VERIFY _log Enabling adds randomization module to project setup.
      Given I logout
      And I login to REDCap with the user "Test_Admin"
      And I click on the link labeled "My Projects"
      And I click on the link labeled "EM Port Project to Remote REDCap Test 1 - Source"
      And I click on the link labeled "Logging"
      Then I should see a table header and rows containing the following values in the logging table:
         | Time / Date      | Username   | Action                                                                   | List of Data Changes OR Fields Exported |
         | mm/dd/yyyy hh:mm | test_admin | Enable external module "port_project_to_remote_redcap_v9.9.9" for project|                                         |

#END