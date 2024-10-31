# Port Project to Remote REDCap

Port Project to Remote REDCap is an external module to enable researchers to move their projects from their REDCap instance to a separate REDCap instance while retaining an audit trail.  
This module ports the project metadata and records to the remote instance, as well as project and external module logs; logs are stored as individual CSV files for each table in a zip file. This zip file is stored in the file repository of the corresponding project on the remote instance.

This module is intended to facilitate one-time migration of a project to a different REDCap instances. If your goal is to regularly sync data between REDCap projects, the [API Sync Module](https://github.com/vanderbilt-redcap/api-sync-module) may be more appropriate for your needs.


## Prerequisites
- REDCap >= 14.6.4

<!-- ## Easy Installation -->
<!-- - Obtain this module from the Consortium [REDCap Repo](https://redcap.vanderbilt.edu/consortium/modules/index.php) from the REDCap Control Center. -->

## Manual Installation
- Clone this repo into `<redcap-root>/modules/port_project_to_remote_instance_v0.0.0`.

## System Configuration

- **Warning Text**: Text that appears at the top of the "Copy Project to Remote REDCap" page, intended to serve as a reminder to double check any data governance policies.
  - If left blank, this field defaults to: "If you are moving your project to a REDCap instance that is outside of your organization's control, please be aware of any data governance policies that may restrict data transfer by institution or region."

## Project Configuration

- **Remote API URI**: The URL for the API endpoint of the REDCap server to which you are trying to port the project, e.g. `https://my.redcap.edu/api/`; this _must_ end with `/api/`.
- **Remote Project Token**: The API token associated with the project to which you intend to transfer data to.

## Use

A new blank project should be created on the remote REDCap server, an API token needs to be created for this project and provided to the module.  
Creating this API token for a user specifically created for API tasks may be helpful for identifying log traffic created by this module.

Set the **Remote API URI** and the generated **Remote Project API Token** in the module settings of the project you wish to migrate to the remote server.

When you are ready to port the project, navigate to the "Copy Project to Remote REDCap" link under the "External Modules" section of the sidebar.

Select the appropriate **Target Server API URI** from the dropdown menu and specify any additional options desired.

- **Flush records**: check this to delete any records in the remote project before starting the transfer process. Typically not needed, but potentially useful if project changes are made and additional data was collected after an initial transfer has been made.

Click the "Transfer project" button to initiate the transfer.

## Potential Limitations

While migration of file upload fields are supported, signature fields **will not be migrated**. This is _explicitly_ forbidden by the REDCap API.

Log records associated with the project are collated locally in temporary files prior to being transferred to the remote instance; it's possible you may be limited by your instance's hardware.  
Additionally, you may be limited by the remote server's file upload settings (by default, the File Repository has "unlimited" capacity but individual files are limited to 128MB).

Migration of data to an _earlier_ version of REDCap is not explicitly supported and may have unexpected behavior.  
Cloud storage of files on is not supported, whether on the local on remote REDCap instances (the latter may work, but this is entirely untested).
