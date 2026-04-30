<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CAAP Form ATS 2019-1 Flight Plan</title>
    <style>
        body.preview {
            background: #ccc;
        }

        .preview-wrapper {
            width: 794px;
            margin: auto;
            background: white;
            padding: 10px;
        }

        @page {
            margin-top: 4mm;
            margin-bottom: 0;
            margin-left: 10mm;
            margin-right: 10mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.0;
            -webkit-text-size-adjust: 100%;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 8px;
            font-size: 10px;
        }

        .header-small {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .form-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
            padding: 4px;
        }

        /* Main form table */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .form-table td, .form-table th {
            padding: 2px 3px;
            vertical-align: top;
            text-align: left;
            height: auto;
        }

        .addressee-box {
            width: 100%;
            height: 60px;
        }

        /* Field boxes for character entry */
        .char-box {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #666;
            border-radius: 2px;
            margin: 1px;
            font-weight: bold;
            font-size: 11px;
            line-height: 15px;
            text-align: center;
            vertical-align: middle;
            color: #000;
        }


        /* Field boxes for string entry */
        .string-box {
            display: inline-block;
            height: 15px;
            border: 1px solid #666;
            line-height: 15px;
            text-align: center;
            vertical-align: middle;
            margin: 1px;
            font-weight: bold;
            font-size: 11px;
            color: #000;
            border-radius: 2px;
            padding: 0 2px;
        }

        .string-box-left {
            text-align: left;
        }

        .checkbox {
            width: 15px;
            height: 15px;
            border: 1px solid #666;
            display: inline-block;
            text-align: center;
            line-height: 14px;
            font-weight: bold;
            font-size: 11px;
            vertical-align: middle;
        }

        /* Label for fields */
        .field-label {
            font-size: 9px;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .section-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data-field {
            font-size: 11px;
            padding: 2px;
            min-height: 16px;
        }

        /* Certification box */
        .certification-box {
            border: 1px solid #000;
            padding: 4px;
            margin-top: 2px;
            font-size: 8px;
            line-height: 1.3;
        }

        .certification-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 2px;
        }
    </style>
</head>
<body class="{{ isset($isPreview) ? 'preview' : '' }}">

    @if(isset($isPreview))
        @if(!(($showPreviewActions ?? true)))
            <div style="width: 794px; margin: 0 auto 10px;">
                @if(session('review_status'))
                    <div
                        id="review-status-banner"
                        style="padding: 10px 12px; border: 1px solid #166534; background: #f0fdf4; color: #166534; font-weight: bold; font-size: 12px;"
                    >
                        {{ session('review_status') }}
                    </div>
                @endif

                @if(($flight->expiration_reason ?? null) && ! session('review_status'))
                    <div
                        id="review-status-banner"
                        style="flex: 1; padding: 10px 12px; border: 1px solid #b45309; background: #fffbeb; color: #b45309; font-weight: bold; font-size: 12px;"
                    >
                        {{ $flight->expiration_reason }}
                    </div>
                @endif
            </div>
        @endif

        @if(($flight->status ?? null) === \App\Enums\FlightPlanStatus::Rejected)
            <div style="width: 794px; margin: 0 auto 12px;">
                <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; background: #fff;">
                    <tr>
                        <th style="width: 22%; border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px;">Rejected by</th>
                        <th style="width: 78%; border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px;">REASON</th>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 11px; font-weight: bold;">
                            {{ $flight->rejected_by_wiresign ?? '' }}
                        </td>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 11px;">
                            {{ $flight->rejection_reason ?? '' }}
                        </td>
                    </tr>
                </table>
            </div>
        @endif

    <div class="preview-wrapper">
    @endif

    <!-- Header -->
    <div class="header">
        <div class="header-small" style="text-align: left; font-size: 9px;">CAAP Form ATS 2019-1</div>
        <div class="header-small">Republic of the Philippines</div>
        <div class="header-small" style="font-weight: bold;">CIVIL AVIATION AUTHORITY OF THE PHILIPPINES</div>
        <div class="header-small">Old MIA Rd. Pasay City, Metro Manila 1300</div>
        <div class="form-title">FLIGHT PLAN</div>
    </div>

    <!-- Main Form -->
    <table class="form-table">
        <!-- Priority and Addressee -->
        <tr>
            <td style="width: 15%; text-align: center; border: 0">
                <span class="section-label">PRIORITY</span><br>
                <span class="data-field">&lt;&lt; = FF</span>
            </td>
            <td style="width: 85%; border: 0">
                <span class="section-label">ADDRESSEE(S)</span><br>
                <div class="addressee-box">
                    <table style="width: 100%; height: 100%; border-collapse: collapse; border: 1px solid #000;">
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <!--- <div style="text-align: right; font-size: 11px;">&lt;&lt; =</div> -->
            </td>
        </tr>

        <!-- Date of Filing and Originator -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse; border: 0;">
                    <tr>
                        <td style="width: 15%;">
                            <span class="field-label">DATE OF FILING</span>
                            <div style="text-align: left; padding: 4px;">
                                @php
                                    $dateFormatted = str_replace('-', '/', (string) ($flight->date_of_filing ?? ''));
                                @endphp
                                <span class="string-box">{{ substr($dateFormatted, 0, 10) }}</span>
                            </div>
                        </td>
                        <td style="width: 85%; padding-left: 4px;">
                            <span class="field-label">ORIGINATOR</span>
                            <div style="text-align: left; padding: 4px;">
                                @php
                                    $originator = str_pad(substr($flight->originator ?? '', 0, 8), 8, ' ');
                                @endphp
                                @foreach(str_split($originator, 1) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Specific Identification -->
        <tr>
            <td colspan="2" style="padding-left: 2px; font-weight: normal; font-size: 7px;">
                <span>SPECIFIC IDENTIFICATION OF ADDRESSEE(S) AND/OR ORIGINATOR</span>
            </td>
        </tr>

        <!-- Blank row -->
        <tr>
            <td colspan="2" style="padding: 2px; border: 1px solid #000; font-weight: normal; font-size: 7px;">
                &nbsp;
            </td>
        </tr>

        <!-- Message Type, Aircraft ID, Flight Rules -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 25%;">
                            <span class="field-label">3. MESSAGE TYPE</span>
                            <div style="text-align: center; padding: 4px;">
                                &lt;&lt;= FPL -
                            </div>
                        </td>
                        <td style="width: 40%; padding: 2px;">
                            <span class="field-label">7. AIRCRAFT IDENTIFICATION</span>
                            <div style="text-align: left; padding: 4px; margin-left: 10px;">
                                @foreach(str_split($flight->aircraft_identification ?? '', 1) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 17.5%; ">
                            <span class="field-label">8. FLIGHT RULES</span>
                            <div style="text-align: left; padding: 4px; margin-left: 30px;">
                                <span class="char-box">{{ substr($flight->flight_rules ?? '', 0, 1) }}</span>
                            </div>
                        </td>
                        <td style="width: 17.5%; ">
                            <span class="field-label">TYPE OF FLIGHT</span>
                            <div style="text-align: left; padding: 4px; margin-left: 30px;">
                                <span class="char-box">{{ substr($flight->type_of_flight ?? '', 0, 1) }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Number, Type of Aircraft, Wake Turbulence, Equipment -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 10%;">
                            <span class="field-label">9. NUMBER</span>
                            <div style="padding: 4px;  text-align: left; margin-left: 10px;">
                                @php
                                    $rawNumber = $flight->number ?? '';
                                    if ($rawNumber === '') {
                                        $number = '';
                                    } elseif (strlen($rawNumber) === 1) {
                                        $number = str_pad($rawNumber, 2, '0', STR_PAD_LEFT);
                                    } else {
                                        $number = substr($rawNumber, 0, 2);
                                    }
                                @endphp
                                @if($number !== '')
                                    @foreach(str_split($number, 1) as $char)
                                        <span class="char-box">{{ $char }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">TYPE OF AIRCRAFT</span>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                @php
                                    $aircraft = str_pad(substr($flight->type_of_aircraft ?? '', 0, 4), 4, ' ');
                                @endphp
                                @foreach(str_split($aircraft, 1) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">WAKE TURBULENCE CAT.</span>
                            <div style="padding: 4px; text-align: left; margin-left: 35px;">
                                <span class="char-box">{{ substr($flight->wake_turbulence_cat ?? '', 0, 1) }}</span>
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 4px;">
                            <span class="field-label">10. EQUIPMENT</span>
                            <div style="padding: 2px; text-align: left; margin-left: 20px;">
                                <span class="string-box" style="width: 120px;">{{ substr($flight->equipment_10a ?? '', 0) }}</span>
                                <span class="char-box" style="border:0;">/</span>
                                <span class="string-box" style="width: 120px;">{{ substr($flight->equipment_10b ?? '', 0) }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Departure Aerodrome and Time -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 5%;">
                            <span class="field-label">&nbsp;</span>
                            <div style="padding: 4px; text-align: center;">
                                &nbsp;
                            </div>
                        </td>
                        <td style="width: 25%;">
                            <span class="field-label">13. DEPARTURE AERODROME</span>
                            <div style="padding: 4px; text-align: center;">
                                @foreach(str_split($flight->departure_aerodrome ?? 'RPUS') as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 10%;">
                            <span class="field-label">&nbsp;</span>
                            <div style="padding: 4px; text-align: center;">
                                &nbsp;
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">TIME</span>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $time = \App\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? '';
                                @endphp
                                @foreach(str_split(substr($time, 0, 4)) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 55%;">
                            <span class="field-label">&nbsp;</span>
                            <div style="padding: 4px; text-align: center;">
                                &nbsp;
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Cruising Speed, Level, Route -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 20%;">
                            <span class="field-label">15. CRUISING SPEED</span>
                            <div style="padding: 4px;  text-align: left; margin-left: 10px;">
                                @php
                                    $cruispeed = str_pad(substr($flight->cruising_speed ?? '', 0, 5), 5, ' ');
                                @endphp
                                @foreach(str_split($cruispeed, 1) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">LEVEL</span>
                            <div style="padding: 4px;  text-align: left; margin-left: -1px;">
                                @php
                                    $lvl = str_pad(substr($flight->level ?? '', 0, 4), 4, ' ');
                                @endphp
                                @foreach(str_split($lvl, 1) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 60%;">
                            <span class="field-label">ROUTE</span>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $routeWords = preg_split('/\s+/', trim((string) ($flight->route ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                @endphp
                                @foreach($routeWords as $routeWord)
                                    <span class="string-box">{{ $routeWord }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Destination, EET, Alternates -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 25%;">
                            <span class="field-label">16. DESTINATION AERODROME</span>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                @foreach(str_split($flight->destination_aerodrome ?? '') as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">TOTAL EET</span>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $eet = \App\Rules\UtcFourDigitTime::formatForDisplay($flight->total_eet) ?? '';
                                @endphp
                                @foreach(str_split(substr($eet, 0, 4)) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 25%;">
                            <span class="field-label">ALTN. AERODROME</span>
                            <div style="padding: 4px; text-align: left;">
                                @foreach(str_split($flight->altn_aerodrome_1 ?? '') as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 30%;">
                            <span class="field-label">2nd ALTN. AERODROME</span>
                            <div style="padding: 4px; text-align: left;">
                                @foreach(str_split($flight->altn_aerodrome_2 ?? '') as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Other Information and PBN -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td>
                            <span class="field-label">18. OTHER INFORMATION</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                @php
                                    $tagHierarchy = [
                                        ['tag' => 'DOF', 'column' => 'other_info_dof'],
                                        ['tag' => 'RMK', 'column' => 'other_info_rmk'],
                                        ['tag' => 'TYP', 'column' => 'other_info_typ'],
                                        ['tag' => 'DEP', 'column' => 'other_info_dep'],
                                        ['tag' => 'RTE', 'column' => 'other_info_route'],
                                        ['tag' => 'DEST', 'column' => 'other_info_dest'],
                                        ['tag' => 'ALTN', 'column' => 'other_info_altn_1'],
                                        ['tag' => 'ALTN2', 'column' => 'other_info_altn_2'],
                                        ['tag' => 'PBN', 'column' => 'other_info_pbn'],
                                        ['tag' => 'REG', 'column' => 'other_info_reg'],
                                        ['tag' => 'OPR', 'column' => 'other_info_opr'],
                                    ];
                                    $tagPairs = [];
                                    foreach ($tagHierarchy as $tagInfo) {
                                        $value = $flight->{$tagInfo['column']} ?? null;
                                        if ($value !== null && trim($value) !== '') {
                                            $tagPairs[] = $tagInfo['tag'] . '/' . $value;
                                        }
                                    }
                                @endphp
                                @foreach($tagPairs as $tagPair)
                                    <span class="string-box">{{ $tagPair }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            &nbsp;
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Supplementary Information Header -->
        <tr>
            <td colspan="2" style="padding: 2px; border: 1px solid #000; text-align: center; font-weight: normal; font-size: 7px;">
                SUPPLEMENTARY INFORMATION (NOT TO BE TRANSMITTED IN FPL MESSAGES)
            </td>
        </tr>

        <!-- Endurance and Survival Equipment -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 35%; text-align: left;">
                            <span class="field-label">19. ENDURANCE</span>
                            <div class="field-label" style="font-weight: normal; text-align: left; padding-left: 70px;">HR  :  MIN</div>
                            <div style="padding: 4px; text-align: center;">
                                <div style="text-align: left;">
                                    @php
                                        $endur = \App\Rules\UtcFourDigitTime::formatForDisplay($flight->endurance) ?? '';
                                    @endphp
                                    <span class="char-box" style="border: 0">E</span>
                                    <span class="char-box" style="border: 0">/</span>
                                    @foreach(str_split(substr($endur, 0, 4)) as $char)
                                        <span class="char-box">{{ $char }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td style="width: 35%; text-align: left;">
                            <span class="field-label">PERSONS ON BOARD</span>
                            <div class="field-label" style="text-align: left;">&nbsp;</div>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $pob = str_pad($flight->persons_on_board ?? '', 3, '0', STR_PAD_LEFT);
                                @endphp
                                <span class="char-box" style="border: 0">P</span>
                                <span class="char-box" style="border: 0">/</span>
                                @foreach(str_split($pob) as $char)
                                    <span class="char-box">{{ $char }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td style="width: 30%; text-align: left;">
                            <span class="field-label">EMERGENCY RADIO</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">UHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">VHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">ELT</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">R</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_uhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_vhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_elt ? 'X' : '' }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Survival Equipment Grid -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; text-align: left;">
                            <span class="field-label">SURVIVAL EQUIPMENT</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">POLAR</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">DESERT</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">MARITIME</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">JUNGLE</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">S</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_polar ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_desert ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_maritime ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_jungle ? 'X' : '' }}</span>
                            </div>
                        </td>
                        <td style="width: 50%; text-align: left;">
                            <span class="field-label">JACKETS</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">LIGHT</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">FLUORES</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">UHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">VHF</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">S</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->jackets_light ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_fluores ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_uhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_vhf ? 'X' : '' }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Dinghies and Colors -->
        <tr>
            <td colspan="2">

            </td>
        </tr>

        <!-- Aircraft Colour and Markings, Remarks, and Pilot in Command with QR Code -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 80%; vertical-align: top;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">DIGHHIES</span>
                                        <div>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">NUMBER</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: left;">CAPACITY</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">COVER</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: left;">COLOUR</span>
                                        </div>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">D</span>
                                            <span class="char-box" style="border: 0">/</span>
                                                @php
                                                    if ($flight->dinghies_enabled) {
                                                        $dingnum = str_pad($flight->dinghies_number ?? '', 2, '0', STR_PAD_LEFT);
                                                    } else {
                                                        $dingnum = 'XX';
                                                    }
                                                @endphp
                                                @foreach(str_split($dingnum) as $char)
                                                    <span class="char-box">{{ $char }}</span>
                                                @endforeach
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                                @php
                                                    if ($flight->dinghies_enabled) {
                                                        $dingcap = str_pad($flight->dinghies_capacity ?? '', 3, '0', STR_PAD_LEFT);
                                                    } else {
                                                        $dingcap = 'XXX';
                                                    }
                                                @endphp
                                                @foreach(str_split($dingcap) as $char)
                                                    <span class="char-box">{{ $char }}</span>
                                                @endforeach
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box">{{ $flight->dinghies_enabled ? ($flight->dinghies_cover ?? 'X') : 'X' }}</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="string-box">{{ $flight->dinghies_enabled ? ($flight->dinghies_color ?? 'X') : 'X' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">AIRCRAFT COLOUR AND MARKINGS</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">A</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->aircraft_colour_and_markings ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">REMARKS</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">N</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->remarks ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">PILOT IN COMMAND</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">C</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->pilot_in_command ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <!-- QR Code Section -->
                        <td style="width: 20%; padding-left: 4px; vertical-align: top; text-align: center;">
                            <span class="field-label" style="margin-bottom: 46px;"></span>
                            <div style="padding: 4px; border: 1px solid #000;">
                                @if(isset($qrCodeBase64))
                                    <img src="{{ $qrCodeBase64 }}" style="width: 120px; height: 120px;" />
                                @elseif(isset($isPreview) && ($showPreviewActions ?? true))
                                    <div style="width: 120px; height: 120px; border: 1px dashed #666; display: flex; align-items: center; justify-content: center; margin: 0 auto; padding: 8px; box-sizing: border-box; font-size: 9px; line-height: 1.3; text-align: center; color: #444;">
                                        QR will be generated when the PDF is generated.
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Certification -->
        <tr>
            <td colspan="2">
                <div class="certification-box">
                    <div class="certification-title">CERTIFICATION</div>
                    <p style="margin: 0;">
                        This is to certify that the above entries are true and correct and that, pilot-in-command of this aircraft, pledge not to fly over prohibited and restricted areas; will not willfully deviate from the filed flight plan, except when necessary in the interest of safety; will operate only in accordance with existing Civil and Military regulations; and will not operate in any manner inimical to the security of the Republic of the Philippines. The herein Pilot-in-Command is qualified to fly the route mentioned in this Flight Plan.
                    </p>
                </div>
            </td>
        </tr>

        <!-- Filed By -->
        <tr style="padding-bottom: 4px;">
            <td colspan="2" style="padding: 4px;">
                <span class="field-label">FILED BY:</span>
                <br />
                <br />
            </td>
        </tr>

        <!-- Signature Section -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 23%;  padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->pilot_in_command ?? '' }}</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->pilot_license_no ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->pilot_ratings ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->license_expiry_date ?? '' }}</span>
                        </td>
                        <td style="width: 6%; text-align: center;">
                            OR
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_name ?? '' }}</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_id_license ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_expiry_date ?? '' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">PILOT'S NAME AND SIGNATURE</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">LICENSE NO. / RATING / EXPIRY DATE</span>
                        </td>
                        <td style="width: 6%; text-align: center;">
                            <span style="font-weight: normal;">&nbsp;</span>
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">AUTH. REP'S NAME AND SIGNATURE</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">LICENSE NO. / EXPIRY DATE</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- CAAP Acceptance -->
        <tr style="border: 1px solid #000;">
            <td colspan="2" style="padding: 1px; font-weight: bold; text-align: center; font-size: 8px;">
                CAAP ACCEPTANCE
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="font-size: 11px;">
                        <td style="width: 33.33%; #000; padding: 4px;">
                            <span class="field-label">RECEIVED BY:</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                {{ $flight->received_by ?? '' }}
                            </div>
                        </td>
                        <td style="width: 33.33%; #000; padding: 4px;">
                            <span class="field-label">DATE/TIME FILED:</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                @php
                                    $receivedDate = $flight->received_date ?? null;
                                    $receivedTime = $flight->received_time ?? null;
                                @endphp
                                @if($receivedDate || $receivedTime)
                                    {{ trim(collect([
                                        $receivedDate,
                                        $receivedTime ? (\App\Rules\UtcFourDigitTime::formatForDisplay($receivedTime) ?? trim((string) $receivedTime)).' Z' : null,
                                    ])->filter()->implode(' ')) }}
                                @endif
                            </div>
                        </td>
                        <td style="width: 33.34%; padding: 4px;">
                            <span class="field-label">FACILITY/AIRPORT</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                {{ $flight->received_facility ?? '' }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>    

    @if(isset($isPreview))
    </div>
    @endif

    @if(isset($isPreview) && ($showPreviewActions ?? true))
    <div style="display:flex; justify-content:center; gap:12px; margin-top:10px;">
        <form method="POST" action="{{ route('flightplan.approve') }}">
            @csrf
            <button type="submit">GENERATE PDF</button>
        </form>

        <form method="POST" action="{{ route('flightplan.edit-preview') }}">
            @csrf
            <button type="submit">EDIT</button>
        </form>

        <form method="POST" action="{{ route('flightplan.discard-preview') }}">
            @csrf
            <button type="submit">DISCARD</button>
        </form>
    </div>
    @endif

    @if(isset($isPreview) && ($showReviewActions ?? false))
    <div style="display:flex; justify-content:center; gap:12px; margin-top:10px;">
        <form method="POST" action="{{ $acceptActionUrl }}">
            @csrf
            <button
                type="submit"
                onclick="return confirm('Accepted by {{ $acceptedByWiresign !== '' ? addslashes($acceptedByWiresign) : 'this ATC user' }}?');"
            >
                ACCEPT
            </button>
        </form>

        <form method="POST" action="{{ $rejectActionUrl }}">
            @csrf
            <input type="hidden" name="rejection_reason" id="rejection-reason-input">
            <button
                type="submit"
                onclick="
                    const wiresign = '{{ $acceptedByWiresign !== '' ? addslashes($acceptedByWiresign) : 'this ATC user' }}';
                    const reason = window.prompt('Reject by ' + wiresign + '. State a short reason:');

                    if (reason === null) {
                        return false;
                    }

                    const trimmedReason = reason.trim();

                    if (trimmedReason === '') {
                        window.alert('A short rejection reason is required.');
                        return false;
                    }

                    document.getElementById('rejection-reason-input').value = trimmedReason;

                    return true;
                "
            >
                REJECT
            </button>
        </form>
    </div>
    @endif

</body>
</html>
