@extends('layout.master')

@section('content')

<div class="row">
    <div class="col-md-12">

        <div class="card">
            <div class="card-body">

                <h4 class="mb-4">Asignación masiva de CUC por sector</h4>

                {{-- Mensaje correcto --}}
                @if (Session::has('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ Session::get('success') }}

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="alert">
                        </button>
                    </div>
                @endif

                {{-- Mensaje de error --}}
                @if (Session::has('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ Session::get('error') }}

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="alert">
                        </button>
                    </div>
                @endif

                {{-- Errores de validación --}}
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="alert">
                        </button>
                    </div>
                @endif

                <div class="row">

                    {{-- EXPORTAR ARCHIVO --}}
                    <div class="col-md-6 mb-4">

                        <div class="card border">
                            <div class="card-body">

                                <h5 class="mb-3">
                                    Exportar archivo CUC
                                </h5>

                                <form action="{{ route('reporte.guardarcuc') }}"
                                      method="POST">

                                      @csrf

                                    <div class="mb-3">
                                        <label for="sector_exportar"
                                               class="form-label">
                                            Seleccione un sector
                                        </label>

                                        <select name="sector_id"
                                                id="sector_exportar"
                                                class="form-select"
                                                required>

                                            <option value="">
                                                Seleccione
                                            </option>

                                            @foreach ($sectores as $sector)
                                                <option value="{{ $sector->id_sector }}">
                                                    {{ $sector->codi_sector }}
                                                </option>
                                            @endforeach

                                        </select>
                                    </div>

                                    <button type="submit"
                                            class="btn btn-success" target="_blank">
                                        <i data-feather="download"></i>
                                        Exportar archivo
                                    </button>

                                </form>

                            </div>
                        </div>

                    </div>

                    {{-- IMPORTAR ARCHIVO --}}
                    <div class="col-md-6 mb-4">

                        <div class="card border">
                            <div class="card-body">

                                <h5 class="mb-3">
                                    Importar archivo CUC
                                </h5>
                                <div id="resultadoImportacion" class="alert d-none" role="status" aria-live="polite"></div>

                                <form action="{{ route('reporte.importarcuc') }}"
                                      method="POST"
                                      enctype="multipart/form-data">

                                    @csrf

                                    <div class="mb-3">
                                        <label for="archivo"
                                               class="form-label">
                                            Archivo Excel
                                        </label>

                                        <input type="file"
                                               name="archivo"
                                               id="archivo"
                                               class="form-control"
                                               accept=".xlsx,.xls"
                                               required>
                                    </div>

                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="btnImportar">

                                        <i data-feather="upload"></i>
                                        Importar archivo

                                    </button>

                                </form>

                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

@endsection

@push('custom-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formularioImportacion = document.querySelector(
        'form[action="{{ route('reporte.importarcuc') }}"]'
    );

    if (formularioImportacion) {
        formularioImportacion.addEventListener('submit', async function (event) {
            event.preventDefault();
            const boton = document.getElementById('btnImportar');
            if (boton.disabled) return;
            const etiqueta = boton.innerHTML;
            const resultado = document.getElementById('resultadoImportacion');
            resultado.className = 'alert d-none';
            boton.disabled = true;
            boton.innerHTML = 'Procesando archivo...';
            try {
                const response = await fetch(formularioImportacion.action, {
                    method: 'POST',
                    body: new FormData(formularioImportacion),
                    headers: { 'Accept': 'application/json' }
                });
                const json = (response.headers.get('content-type') || '').includes('application/json')
                    ? await response.json() : null;
                if (!response.ok || !json) {
                    const errores = json?.errors ? Object.values(json.errors).flat().join(' ') : null;
                    throw new Error(errores || json?.message || 'El servidor no completó la respuesta. Revise el resultado antes de volver a importar.');
                }
                resultado.className = 'alert alert-success';
                resultado.textContent = json.message;
            } catch (error) {
                resultado.className = 'alert alert-danger';
                resultado.textContent = error.message || 'No se pudo conectar con el servidor. Revise el resultado antes de volver a importar.';
            } finally {
                boton.disabled = false;
                boton.innerHTML = etiqueta;
            }
        });
    }
});
</script>
@endpush
