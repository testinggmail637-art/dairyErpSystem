# Front-end vendor libraries

Committed minified files, no npm/Vite (spec Section 2.1). Downloaded 2026-10-15 from
`https://cdn.jsdelivr.net/npm/<package>@<version>/...`. Source-map comments were stripped (maps are not shipped).
To upgrade: download the same files for the new version, replace them, update this table, re-test all screens.

| Library | Version | Files |
|---|---|---|
| Bootstrap | 5.3.8 | `bootstrap/css/bootstrap.min.css`, `bootstrap/js/bootstrap.bundle.min.js` |
| Bootstrap Icons | 1.13.1 | `bootstrap-icons/bootstrap-icons.min.css`, `bootstrap-icons/fonts/*` |
| jQuery | 3.7.1 | `jquery/jquery.min.js` |
| Select2 | 4.1.0-rc.0 | `select2/css/select2.min.css`, `select2/js/select2.min.js` |
| select2-bootstrap-5-theme | 1.3.0 | `select2/css/select2-bootstrap-5-theme.min.css` |
| Flatpickr | 4.6.13 | `flatpickr/flatpickr.min.css`, `flatpickr/flatpickr.min.js` |
| DataTables (+ Bootstrap 5 integration) | 2.3.2 | `datatables/js/dataTables.min.js`, `datatables/js/dataTables.bootstrap5.min.js`, `datatables/css/dataTables.bootstrap5.min.css` |
| Chart.js | 4.5.0 | `chartjs/chart.umd.min.js` |
| SweetAlert2 | 11.22.0 | `sweetalert2/sweetalert2.all.min.js` |

Fonts for Settings → Appearance (Inter, Roboto, Poppins, Noto Sans) are added under `fonts/` in Phase 0 · Task 12.
