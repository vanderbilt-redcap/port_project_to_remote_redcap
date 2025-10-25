---
name: Bug report
about: Bug report
title: "[BUG]"
labels: ''
assignees: ''

---

# Bug description
<!-- A clear and concise description of what the bug is. -->

# System information
<!-- When reporting issues, these details are enormously helpful: -->

- Your REDCap, PHP, and MySQL versions (all available on the main Control Center page)
- The OS (and version) of your source and target servers (on Linux servers, see the content of `/etc/os-release`)
   - The output of `free -h` may be helpful as well to determine if it's a system resource issue
- Recent errors if relevant to the module:
```sql
SELECT * FROM redcap_error_log
WHERE error LIKE "%port_project%"
ORDER BY error_id DESC
```
- Any errors in your browser's console.
- If an issue arises only with a particular project, please provide the Project XML for said project
  - GitHub will whine about a dropping an xml file in this issue, but will happily accept a zip containing an xml file.
