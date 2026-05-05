<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Abbreviated Flight Report</title>
    <style>
        @font-face {
            font-family: 'Open Sans Custom';
            src: url('{{ storage_path('app/public/fonts/OpenSans-Var.ttf') }}') format('truetype');
            font-weight: 400 700;
            font-style: normal;
        }

        @font-face {
            font-family: 'Trajan Pro Custom';
            src: url('{{ storage_path('app/public/fonts/TrajanPro-Bold.otf') }}') format('opentype');
            font-weight: 400;
            font-style: normal;
        }

        @page {
            size: A4 landscape;
            margin: 5mm 10mm 8mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111;
            font-size: 9px;
            line-height: 1.2;
        }

        .sheet {
            width: 100%;
        }

        .header {
            width: 100%;
            /*border: 1.5px solid #000;*/
            /*border-bottom: 1px solid #000;*/
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
        }

        .header-logo {
            width: 100px;
            padding: 6px 6px 0px 0px;
            /*border: 1.5px solid #000;*/
        }

        .header-logo img {
            display: block;
            width: 100px;
            /*height: 120px;*/
            margin: 0 auto;
            object-fit: contain;
        }

        .header-copy {
            padding: 2px 0px 0px 10px;
        }

        .agency-line,
        .agency-subline,
        .agency-office {
            margin: 0;
            text-align: left;
        }
/*
        .agency-line {
            vertical-align: middle;
        }
*/
        .agency-line--republic {
            font-family: 'Open Sans Custom', DejaVu Sans, Arial, sans-serif;
            font-weight: 400;
            font-size: 15px;
        }

        .agency-line--caap {
            font-family: 'Trajan Pro Custom', DejaVu Serif, serif;
            font-weight: 700;
            font-size: 18px;
            text-transform: uppercase;
        }

        .agency-subline,
        .agency-office {
            font-size: 9px;
        }

        .report-title {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .report-subtitle {
            margin: 0;
            text-align: center;
            font-size: 8px;
            text-transform: uppercase;
        }

        .header-meta {
            width: 100%;
            border-collapse: collapse;
            padding-bottom: 10px;
            font-size: 10px;
            font-weight: 700;
            text-align: right;
        }

        .header-meta__label {            
            font-weight: 700;
            text-transform: uppercase;
        }

        .header-meta__value {
            border-bottom: 1px solid #000;
            padding-left: 6px !important;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.5px solid #000;
        }

        .report-table thead th {
            border: 1px solid #000;
            padding: 4px 3px;
            text-align: center;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .report-table tbody td {
            border: 1px solid #000;
            /*height: 18px;*/
            padding: 1px 1px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        .center {
            text-align: center;
        }

        .small {
            font-size: 8px;
        }

        .remarks-cell {
            color: #222;
        }

        .footer {
            margin-top: 5px;
            text-align: right;
            font-size: 7px;
        }

        .empty-state td {
            text-align: center;
            font-style: italic;
            height: 36px;
        }
    </style>
</head>
<body>
    @php
        $rowsPerPage = 30;
        $reportDate = isset($selectedDate) && filled($selectedDate)
            ? \Carbon\Carbon::parse($selectedDate)->format('d M Y')
            : $generatedAt->format('d M Y');
        $flightPages = $flights->values()->chunk($rowsPerPage);

        if ($flightPages->isEmpty()) {
            $flightPages = collect([collect()]);
        }

        $totalPages = $flightPages->count();
    @endphp

    @foreach ($flightPages as $pageIndex => $pageFlights)
        @php
            $pageNumber = $pageIndex + 1;
            $blankRows = max(0, $rowsPerPage - $pageFlights->count() - ($pageFlights->isEmpty() ? 1 : 0));
        @endphp

        <div class="sheet" style="{{ $pageNumber > 1 ? 'page-break-before: always;' : '' }}">
            <table class="header">
                <tr>
                    <td class="header-logo">
                        <img src="{{ storage_path('app/public/img/logo-caap.jpg') }}" alt="CAAP Logo">
                    </td>
                    <td class="header-copy">
                        <p class="agency-line agency-line--republic">Republic of the Philippines</p>
                        <p class="agency-line agency-line--caap">Civil Aviation Authority of the Philippines</p>
                        <p class="agency-office">San Fernando Flight Service Station</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p class="report-title">Abbreviated Flight Plans for Air Carriers, General Aviation & Military Aircraft</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="width: 100%; text-align: right; padding-bottom: 5px;">
                        <b>Date: <u>&nbsp;&nbsp; {{ $reportDate }} &nbsp;&nbsp;</u></b>
                    </td>
                </tr>
            </table>

            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 9%;">Call Sign</th>
                        <th style="width: 5%;">Type</th>
                        <th style="width: 8%;">Origin</th>
                        <th style="width: 8%;">Destination</th>
                        <th style="width: 5%;">PTD</th>
                        <th style="width: 5%;">ATD</th>
                        <th style="width: 28%;">Route of Flight</th>
                        <th style="width: 5%;">ETE</th>
                        <th style="width: 5%;">FOB</th>
                        <th style="width: 5%;">POB</th>
                        <th style="width: 19%;">Pilot In Command</th>
                        <th style="width: 8%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pageFlights as $flight)
                        <tr>
                            <td class="mono center">{{ $flight->aircraft_identification }}</td>
                            <td class="center small">{{ $flight->type_of_aircraft }}</td>
                            <td class="center">{{ $flight->departure_aerodrome }}</td>
                            <td class="center">{{ $flight->destination_aerodrome }}</td>
                            <td class="mono center">{{ $formatTime($flight->proposed_time) }}</td>
                            <td class="mono center">{{ $formatTime($flight->time_airborne) }}</td>
                            <td class="mono small">{{ $flight->route }}</td>
                            <td class="mono center">{{ $formatTime($flight->total_eet) }}</td>
                            <td class="mono center">{{ $formatTime($flight->endurance) }}</td>
                            <td class="center">{{ $flight->persons_on_board }}</td>
                            <td class="small">{{ $flight->pilot_in_command }}</td>
                            <td class="remarks-cell"></td>
                        </tr>
                    @empty
                        <tr class="empty-state">
                            <td colspan="12">No abbreviated RPUS flight records available.</td>
                        </tr>
                    @endforelse

                    @for ($row = 0; $row < $blankRows; $row++)
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="footer">
                Generated from Project Echo abbreviated report export | Page {{ $pageNumber }} of {{ $totalPages }}
            </div>
        </div>
    @endforeach
</body>
</html>
