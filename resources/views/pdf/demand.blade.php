<!DOCTYPE html>
<!--
 > demand.blade.php
 > "Demand" blade for DomPDF pdf creation
-->
<html>
<head>

	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<title>{{ $filename }}</title>

	<style type="text/css">

		@page {
			size: 210mm 297mm;
			margin: 20mm;
		}

		body {
			font-family: 'DejaVu Sans';
		}

		.part-head {
			position: absolute;
			font-size: 3mm;
		}

		.part-content {
			position: relative;
			font-size: 3.5mm; font-family: 'DejaVu Serif';
		}

		.qm-path {
			left: 0mm; top: 0mm;
			line-height: 1;
		}

		.qm-title {
			left: 0mm; top: 7mm;
			font-weight: bold;
		}

		.qm-logo {
			right: 0mm; top: 0mm;
			width: 40mm; height: 12mm;
		}

		table, tr, td, th, thead, tbody {
			padding: 0;
		}

		.anf-info {
			left: 0mm; right: 0mm; top: 14mm;
			width: 170mm;
			border: 1px solid black;
			border-collapse: collapse;
		}

    .anf-info td {
      height: 11mm;
    }

		.anf-info .title,
		.anf-info .date {
			font-size: 4mm; font-weight: bold;
		}

		.anf-info .title {
			padding-left: 3mm;
		}

		.anf-info .date {
			width: 55mm;
			border-left: 1px solid black;
			text-align: center;
		}

		.anf-data {
			margin: 26mm auto 0;
			width: 170mm;
			border: 1px solid black;
			border-collapse: collapse;
		}

		.anf-data thead {
			height: 7mm;
			border: 1px solid black;
			display: table-header-group;
		}

		.anf-data thead,
		.anf-data td {
			border-bottom: 1px solid black;
		}

		.anf-data tbody tr {
			text-align: center;
			page-break-inside: avoid;
		}

    .anf-data th {
      height: 7mm;
    }

    .anf-data td {
      height: 9mm;
    }

		.anf-data thead .col-amount,
		.anf-data thead .col-ok,
		.anf-data thead .col-change,
		.anf-data tbody .col-amount,
		.anf-data tbody .col-ok,
		.anf-data tbody .col-change {
			border-left: 1px solid black;
		}

		.anf-data thead .col-name,
		.anf-data thead .col-amount {
			background-color: #ccc;
		}

		.anf-data thead .col-name,
		.anf-data tbody .col-name {
			text-align: left;
			padding-left: 3mm;
		}

		.anf-data thead .col-amount,
		.anf-data tbody .col-amount {
			width: 38mm;
		}

		.anf-data thead .col-ok,
		.anf-data thead .col-change {
			background-color: #eee;
		}

		.anf-data thead .col-ok,
		.anf-data tbody .col-ok {
			width: 20mm;
		}

		.anf-data thead .col-change,
		.anf-data tbody .col-change {
			width: 28mm;
		}

		.anf-data tbody .col-ok--box {
			border: 1px solid black;
			height: 5mm;
			width: 5mm;
			margin: auto;
		}

	</style>

</head>
</head>
<body>

	<div class="part-head qm-path">Malteser Hilfsdienst/Rettungsdienst/Region NO/Bezirk Dresden/<br>Rettungsdienst Arbeitsdokument</div>
	<div class="part-head qm-title">FO RD NO DD 60 VA04 2.7 Bedarfsliste</div>
	<img class="part-head qm-logo" src="{{ public_path('/assets/pdfs/mltsr-logo.png') }}">

	<table class="part-head anf-info">
		<tr>
			<td class="title">
				{{ $demand_title }}
			</td>
			<td class="date">
				{{ $demand_date }}
			</td>
		</tr>
	</table>

	<table class="part-content anf-data">
		<thead>
			<tr>
				<th class="col-name">Was soll bestellt werden?</th>
				<th class="col-amount">Wieviel?</th>
				<th class="col-ok">Gepackt?</th>
				<th class="col-change">Änderungen</th>
			</tr>
		</thead>
		<tbody>
      @foreach($items as $item)
        <tr>
          <td class="col-name">{{ $item['name'] }}</td>
          <td class="col-amount">{{ $item['amount'] }}</td>
          <td class="col-ok">
            <div class="col-ok--box">&nbsp;</div>
          </td>
          <td class="col-change">&nbsp;</td>
        </tr>
      @endforeach
		</tbody>
	</table>

</body>
</html>