@push('custom-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formularioImportacion = document.querySelector(
        'form[action="{{ route($rutaImportacion) }}"]'
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
            const controller = new AbortController();
            const inicio = Date.now();
            const contador = setInterval(() => {
                boton.textContent = 'Procesando archivo... ' + Math.floor((Date.now() - inicio) / 1000) + ' s';
            }, 1000);
            const limite = setTimeout(() => controller.abort(), 120000);
            try {
                const response = await fetch(formularioImportacion.action, {
                    method: 'POST',
                    signal: controller.signal,
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
                resultado.textContent = error.name === 'AbortError'
                    ? 'El servidor no respondió en 2 minutos. Esto no confirma que la carga se haya cancelado. Revise el resultado y el registro de errores del servidor antes de repetirla.'
                    : (error.message || 'No se pudo conectar con el servidor. Revise el resultado antes de volver a importar.');
            } finally {
                clearInterval(contador);
                clearTimeout(limite);
                boton.disabled = false;
                boton.innerHTML = etiqueta;
            }
        });
    }
});
</script>
@endpush
