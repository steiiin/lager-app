<!DOCTYPE html>
<!--
 > labels.blade.php
 > "Labels" blade for DomPDF pdf creation
-->
<html>
<head>

	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<title>{{ $filename }}</title>

	<style type="text/css">

 		@font-face {
			font-family: 'Barcode';
			font-style: normal;
			font-weight: normal;
			src: url('{{ public_path("assets/fonts/barcode-font.ttf") }}') format('truetype');
    }

		@page {
			size: 210mm 297mm;
			margin: 20mm;
		}

		body {
			font-family: 'DejaVu Sans';
			white-space: collapse;
			font-size: 0;
			padding: 0; margin: 0;
		}

		body .label {
			display: inline-block;
		}

		.label {
			border-bottom: 1px dotted black;
			border-top: 1px dotted black;
			width: 75.5mm; height: 21mm;
			position: relative;
			top: 0; left: 0; margin-left: 1mm;
		}

		.label .name {
			position: absolute;
			top: 1.5mm; left: 5mm; width: 65mm; height: 6mm;
			font-size: 4mm;
			letter-spacing: -1px;
			overflow: hidden;
		}
		.label .barcode {
			position: absolute;
			top: 7mm; left: 5mm;
			font-size: 8.5mm;
			font-family: 'Barcode';
			transform: scaleY(1.6);
			transform-origin: 0 0;
		}


		.label .item-size {
			position: absolute;
			right: 2.5mm; top: 11.5mm;
			width: 17.5mm; text-align: center;
			font-size: 3.5mm; font-weight: bold;
		}

		.label .item-size-warn {
			position: absolute;
 			right: 2.5mm; top: 7.6mm;
			height: 10.2mm; width: 17.5mm;
			background-color: lightgray;
		}

		.label .ctrl-symbol {
			position: absolute;
			left: 5mm; top: 7.5mm;
			height: 10.5mm; width: 10.5mm;
		}

		.label-ctrl .name {
			width: 50mm;
		}

		.label-ctrl .name,
		.label-ctrl .barcode {
			left: 20mm;
		}

	</style>

</head>
</head>
<body>

	@foreach($labels as $label)
		<div class="label label-{{ $label['type'] }}">

			<div class="name">{{ $label['name'] }}</div>
			<div class="barcode">*{{ $label['code'] }}*</div>

			@if ($label['type'] == 'item')

				@if ($label['size_warn'])

					<div class="item-size-warn">&nbsp;</div>

				@endif

				<div class="item-size">{{ $label['size'] }}</div>

			@elseif ($label['type'] == 'ctrl')

				<img class="ctrl-symbol" src="{{ public_path('/assets/pdfs/' . $label['symbol'] . '.svg') }}">

			@endif

		</div>
	@endforeach

</body>
</html>