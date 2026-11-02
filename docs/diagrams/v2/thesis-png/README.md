# Thesis diagram PNGs

These are landscape PNG exports for the diagram figures listed in
`Old-Diagrams-Compilation.docx`. Figure captions are intended to remain in the
Word document; the images do not add a separate title banner.

| Figure | PNG | Source |
|---|---|---|
| 2 | `figure-02-network-design.png` | `thesis-source/figure-02-network-design.mmd` |
| 8 | `figure-08-context.png` | `thesis-source/figure-08-context.mmd` |
| 9 | `figure-09-level0.png` | `thesis-source/figure-09-level0.mmd` |
| 10 | `figure-10-1A-manage-employee.png` | `thesis-source/figure-10-1A-manage-employee.mmd` |
| 11 | `figure-11-1B-manage-attendance.png` | `thesis-source/figure-11-1B-manage-attendance.mmd` |
| 12 | `figure-12-1C-manage-salary.png` | `thesis-source/figure-12-1C-manage-salary.mmd` |
| 13 | `figure-13-1D-manage-deductions.png` | `thesis-source/figure-13-1D-manage-deductions.mmd` |
| 14 | `figure-14-1E-manage-payroll-processing.png` | `thesis-source/figure-14-1E-manage-payroll-processing.mmd` |
| 15 | `figure-15-1F-manage-generate-payslip.png` | `thesis-source/figure-15-1F-manage-generate-payslip.mmd` |
| 16 | `figure-16-1G-work-schedule-management.png` | `thesis-source/figure-16-1G-work-schedule-management.mmd` |
| 17 | `figure-17-1H-request-management.png` | `thesis-source/figure-17-1H-request-management.mmd` |
| 18 | `figure-18-1I-attendance-self-service.png` | `thesis-source/figure-18-1I-attendance-self-service.mmd` |
| 19 | `figure-19-1J-view-receive-payslip.png` | `thesis-source/figure-19-1J-view-receive-payslip.mmd` |
| 20 | `figure-20-1K-login-authentication.png` | `thesis-source/figure-20-1K-login-authentication.mmd` |
| 21 | `figure-21-1L-manage-users.png` | `thesis-source/figure-21-1L-manage-users.mmd` |
| 22 | `figure-22-1M-manage-approved-payroll.png` | `thesis-source/figure-22-1M-manage-approved-payroll.mmd` |
| 23 | `figure-23-1N-manage-reports.png` | `thesis-source/figure-23-1N-manage-reports.mmd` |

Figure 18 is labeled “Submit Attendance” in the old document, but the current
system lets employees view their own attendance; HR imports and adjusts records.
This PNG therefore depicts employee attendance self-service. Update the Word
caption if the figure is renamed there.

To rerender, run `thesis-source/render-thesis-png.ps1` through npm exec with
`@mermaid-js/mermaid-cli` available. The renderer uses a white background and
exports at 2400 px on the long edge.
