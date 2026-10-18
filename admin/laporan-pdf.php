<?php
// Download PDF laporan pemasukan: ?from=YYYY-MM-DD&to=YYYY-MM-DD  (atau ?range=hari_ini|7hari|30hari)
require __DIR__ . '/_l.php';
require __DIR__ . '/../includes/report.php';
[$from, $to] = report_range($_GET, 'hari_ini');
$pdf = build_report_pdf(report_data($d, $from, $to), (string) ($_SESSION['staff_name'] ?? ''));
$name = 'Laporan-Pemasukan-RentalPsKu-' . $from . ($from === $to ? '' : '_sd_' . $to) . '.pdf';
$pdf->Output('D', $name);
