# CHR monitoring template

`chr-monitoring.xlsx` is the header-only copy of the supplied
`Monitoring-of-Assigned-Cases-New-and-Pending-for-Registration.xlsx`.
Sample records, drawings, comments and original document metadata were removed.
The grouped headers, column widths and landscape print settings are retained;
the final Submitted To header is merged vertically so its label prints in full.

`ChrMonitoringExportService` fills one worksheet per investigator. The heading
uses the account username for unambiguous import matching, with the display name
on the following line. Long case text stays unmerged, once per case, because Excel
does not auto-fit vertically merged text. Docket and date cells span party rows.
The exporter sets the print area and repeats the eleven header rows on each page.

The workbook contains CHR monitoring fields, not a complete CaseTrack backup.
CaseTrack progress status and complexity are absent from the original template.
Import remains create-only and reads only the active worksheet.
