<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Ops Log</title>
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
            font-family: 'Open Sans Custom', DejaVu Sans, Arial, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 0.9;
        }

        .sheet {
            width: 100%;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
        }

        .header-logo {
            width: 100px;
            padding: 6px 6px 0 0;
        }

        .header-logo img {
            display: block;
            width: 100px;
            margin: 0 auto;
            object-fit: contain;
        }

        .header-copy {
            padding: 2px 0 0 10px;
        }

        .agency-line,
        .agency-office {
            margin: 0;
            text-align: left;
        }

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

        .agency-office {
            font-size: 9px;
        }

        .report-title {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
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
            padding: 1px 1px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .mono {
            font-family: 'Open Sans Custom', DejaVu Sans, Arial, sans-serif;
        }

        .center {
            text-align: center;
        }

        .small {
            font-size: 8px;
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
                        <p class="report-title">Post Operations Log</p>
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
                        <th style="width: 7%;">T/G</th>
                        <th style="width: 9%;">Callsign</th>
                        <th style="width: 7%;">Take-off</th>
                        <th style="width: 7%;">Landing</th>
                        <th style="width: 7%;">Overfly</th>
                        <th style="width: 8%;">Origin</th>
                        <th style="width: 8%;">Destination</th>
                        <th style="width: 7%;">Type</th>
                        <th style="width: 30%;">Nature</th>
                        <th style="width: 10%;">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pageFlights as $flight)
                        <tr>
                            <td class="center"></td>
                            <td class="mono center">{{ $flight->aircraft_identification }}</td>
                            <td class="mono center">{{ $formatTime($flight->time_airborne) }}</td>
                            <td class="mono center">{{ $formatTime($flight->time_touchdown) }}</td>
                            <td class="center"></td>
                            <td class="center">{{ $flight->departure_aerodrome }}</td>
                            <td class="center">{{ $flight->destination_aerodrome }}</td>
                            <td class="center">{{ $flight->type_of_aircraft }}</td>
                            <td class="center">{{ $flight->route }}</td>
                            <td class="center"></td>
                        </tr>
                    @empty
                        <tr class="empty-state">
                            <td colspan="10">Traffic NIL</td>
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
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="footer">
                Generated from Project Echo post ops log export | Page {{ $pageNumber }} of {{ $totalPages }}
            </div>
        </div>
    @endforeach
</body>
</html>
