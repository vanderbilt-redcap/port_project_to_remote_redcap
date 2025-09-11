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
- **Target Remote Server Credentials**: Used to facilitate creation of a target project on the remote REDCap server. The target project is created from the source project's XML[^xml_import].
  - **Remote API URI**: The URL for the API endpoint of the REDCap server to which you are trying to port the project, e.g. `https://my.redcap.edu/api/`; this _must_ end with `/api/`.
  - **Remote Super Token**[^api_token_revocation_rec]: The Super API token associated with the remote REDCap instance, this token allows automated target project creation.

## Project Configuration

- **Remote API URI**: The URL for the API endpoint of the REDCap server to which you are trying to port the project, e.g. `https://my.redcap.edu/api/`; this _must_ end with `/api/`.
- **Remote Project Token**[^api_token_revocation_rec]: The API token associated with the project to which you intend to transfer data to.

## Use

### Creating a Target Project on the Remote Instance

If you are able, it is highly recommended to create a Super API token[^api_token_user] on the target server.

#### With a Super API Token

1. Set up System Configuration with credentials for your target instance.
1. Activate this module on the source project(s) on the REDCap server you wish to migrate from; you will **not** need to configure the module at the project level.
1. Navigate to the Control Center EM page "Port Project - XML" and select the target server and source project
1. Click the "Create Project on Remote Server"
1. A link will appear titled "Go to Project Port Page", click it and proceed to [Porting the Source Project](#porting-the-source-project).


#### Without a Super API Token[^xml_import]

A new project should be created on the remote REDCap server (ideally from a metadata only XML export of the source project), an API token[^api_token_user] needs to be created for this project and provided to the module.  

Set the **Remote API URI** and the generated **Remote Project API Token** in the module settings of the project you wish to migrate to the remote server.

When you are ready to port the project, navigate to the "Copy Project to Remote REDCap" link under the "External Modules" section of the sidebar.

### Porting the Source Project

Select the appropriate **Target Server API URI** from the dropdown menu and specify any additional options desired.


Click the "Transfer project" button to initiate the transfer. Statuses for each step will appear and report their progress.
- `update_remote_project_design`: porting fields, instruments, arm/events mapping and repeating status. This task must complete before record porting is possible.
  - **Retain Project title**: check this to retain the target project's title. If created with a Super API token, the target project will be titled "PPtRR TARGET -- \<source project ID\> -- \<source project title\>".
  - **Delete remote records before importing**: check this to delete any records in the remote project before starting the transfer process. Typically not needed, but potentially useful if project changes are made and additional data was collected after an initial transfer has been made.
  - **Delete remote user roles before importing**: Potentially needed to allow re-import of users if any have a role in the target project.
- `port_users`: Users with access to the project and roles.
  - **Delete remote user roles before importing**: Potentially needed to allow re-import of users if any have a role in the target project.
  - Users do _not_ need to exist on the target server at the time of porting.
  - Any user roles set in the target project will be deleted and replaced with those of the source project.
- `port_records`: Records are ported in batches of 10 to prevent issues with hardware limitations.
  - **Delete remote records before importing**: check this to delete any records in the remote project before starting the transfer process. Typically not needed, but potentially useful if project changes are made and additional data was collected after an initial transfer has been made.
  - **Port file fields**: port the contents of file upload fields (except signatures). This may be time intensive in projects with a large number of files, toggleable to same time on repeat runs.
- `port_file_repository`: All contents of the source project's file repository, as well as directory structure will be ported.
  - The module will create a reserved folder for itself in the target project titled "PPtRR_reserved_folder".
  - Running this repeatedly will result in duplicating files and directories.
- `store_logs`: Create a zip file containing logs relevant to the source project. This zip file will timestamped and stored in the target project's reserved file repository as <YYYY-MM-DD_HH.MM.SS-logs.zip>. All logs named after the associated table and are filtered with `WHERE project_id = <source project id>`.
  - `redcap_projects.csv`
  - `redcap_log_event.csv`
  - `redcap_data_quality.csv`: A combination of `redcap_data_quality_status` and `redcap_data_quality_resolution`, `INNER JOIN`'d on `status_id`
    - These tables contain comments associated with fields.
    - `event_id` is not currently disambiguated.
  - `redcap_external_module_settings.csv`: All entries in this table have the module's `directory_prefix` stored as an additional column for disambiguation.
    - Modules will not be activated in the target project, nor will their settings be automatically populated. A companion module may be created to support this task.
  - `redcap_external_modules_log.csv`[^em_module_logs_table_as_db]: All entries in this table have the module's `directory_prefix` stored as an additional column for disambiguation.

## Potential Limitations

While migration of file upload fields are supported, signature fields **will not be migrated**. This is _explicitly_ forbidden by the REDCap API. The signatures themselves will be stored in the reserved File Repository folder.

Log records associated with the project are collated locally in temporary files prior to being transferred to the remote instance; it's possible you may be limited by your instance's hardware.  
Additionally, you may be limited by the remote server's file upload settings (by default, the File Repository has "unlimited" capacity but individual files are limited to 128MB).

If the source project imposed data quality rules _after_ collecting data that _doesn't_ meet those data quality rules, those data will _not_ be transferred to the target project.

Logs are **not** injected into the target project's relevant tables, this may alter expected behavior of some external modules.[^em_module_logs_table_as_db]

Migration of data to an _earlier_ version of REDCap is not explicitly supported and may have unexpected behavior.  
Cloud storage of files is not _explicitly_ supported whether on the local or remote REDCap instances (the latter may work, but this is entirely untested).

The contents of the history icon next to fields will be erased, this is considered data quality and **is not retained.**

---
# Footnotes

[^api_token_revocation_rec]: It is reccomended to revoke any tokens associated with migration after all project migration steps are complete.
  
[^xml_import]: If not starting from an XML import that you will lose most features of your project (reports, dashboards, survey settings), but you should not lose data associated with records.

[^api_token_user]: Creating this API token for a user specifically created for API tasks is highly recommended to aid in identifying log traffic created by this module.

[^em_module_logs_table_as_db]: A small number of modules use this table as a "database", as this table is not injected into the remote instance, you may see odd behavior after transfer.
