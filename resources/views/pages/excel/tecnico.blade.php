<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ASIGNACION DE FIRMA TECNICO</title>
    <style>
    </style>
</head>
<body>
<div class="container-fluid">
                <!-- Ejemplo de tabla Listado -->
    <div class="card">
        <div class="row">
            <div class="table-responsive" >
            <table  class="table table-bordered table-striped table-sm">
                <tbody>
                    <tr>
                        <th>cod_referencia</th>
                        <th>manzana</th>
                        <th>lote</th>
                        <th>nume_doc</th>
                        <th>fecha_levantamiento</th>
                    </tr>
                    @foreach($cucs as $cuc)
                        <tr>
                            <td>{{ $cuc->id_uni_cat }}</td>
                            <td>{{ $cuc->codi_mzna }}</td>
                            <td>{{ $cuc->codi_lote }}</td>
                            <td>{{ $cuc->nume_doc }}</td>
                            <td>{{ $cuc->fecha_levantamiento }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
</body>

</html>
