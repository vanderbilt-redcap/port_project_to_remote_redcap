Feature: Global Configuration Setting 5: The system shall support setting File Size Limit (MB).
         As a REDCap end user I want to see that the File Size Limit (MB) works as expected.

      Scenario: Global Configuration Setting 5: The system shall support setting the File Size Limit (MB).
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
      When I enter "0" into the field labeled "File Size Limit (MB):"
      And I click on the button labeled "Save"
      Then I should see "Modules Currently Available on this System"

   #SETUP Create Source Project with Module enabled
      When I click on the link labeled "My Projects"
      And I create a new project named "EM Port Project to Remote REDCap Test 1 - Source" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "Project_1.xml", and clicking the "Create Project" button

   #SETUP Create Destination Project
      When I click on the link labeled "My Projects"
      And I click on the link labeled "New Project"
      And I enter "EM Port Project to Remote REDCap Test 1 - Destination" into the field labeled "Project title" 
      And I select "Practice / Just for fun" on the dropdown field labeled "Project's purpose:"
      And I click on the button labeled "Create Project"
      Then I should see "EM Port Project to Remote REDCap Test 1 - Destination"
      When I click on the link labeled "API"
      And I click on the button labeled "Create API token now"
      Then I should see "The API token below is ONLY for you and will work ONLY with this project"
      #We need to figure out a way to get this specific API token as it changes every time we run a test. This token must be added to the source project in the steps below.
  
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
      When I click on the button labeled "Configure"
      And I enter "Last copied API Token" into the field labeled "Remote Project Token:"
      #This goes with the API comment above.
      And I enter "http://127.0.0.1/api/" into the field labeled "Remote API URI:"
      And I click on the button labeled "Save"
      Then I should see "Copy Project to Remote REDCap"
   #VERIFY log Enabling and configuring the port project module.
      When I click on the link labeled "Logging"
      Then I should see a table header and rows containing the following values in the logging table:
         | Time / Date      | Username   | Action                                                                                     | List of Data Changes OR Fields Exported |
         | mm/dd/yyyy hh:mm | test_admin | Modify configuration for external module "port_project_to_remote_redcap_v9.9.9" for project|                                         |
         | mm/dd/yyyy hh:mm | test_admin | Enable external module "port_project_to_remote_redcap_v9.9.9" for project                  |                                         |

      When I click on the link labeled "Copy Project to Remote REDCap"
      And I click on the button labeled "Transfer project"
      Then I should see "Transfer project"
         
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