/**
 * Control de Pedidos · Plásticos Carmen
 *
 * Livewire 4 ya trae Alpine: aqui solo se agregan las piezas propias.
 *  - Tema claro/oscuro recordado por equipo.
 *  - Avisos (toasts) que disparan los componentes con $this->dispatch('aviso', ...).
 *  - Latido de trazabilidad (mismo mecanismo que central.plasticoscarmen.com).
 *  - Atajo Ctrl+K / Cmd+K para la busqueda global.
 */

// ---------- Tema ----------
const TEMA = 'cp-tema';
function aplicarTema(tema) {
    const oscuro = tema === 'oscuro' || (tema === 'sistema' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.tema = oscuro ? 'oscuro' : 'claro';
}
window.temaActual = () => { try { return localStorage.getItem(TEMA) || 'sistema'; } catch { return 'sistema'; } };
window.cambiarTema = (tema) => { try { localStorage.setItem(TEMA, tema); } catch {} aplicarTema(tema); };
aplicarTema(window.temaActual());
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => aplicarTema(window.temaActual()));

// ---------- Alpine: avisos, utilidades ----------
document.addEventListener('alpine:init', () => {
    window.Alpine.store('avisos', {
        lista: [],
        agregar(tipo, mensaje, duracion = 4800) {
            const id = Date.now() + Math.random();
            this.lista.push({ id, tipo, mensaje });
            setTimeout(() => this.quitar(id), duracion);
        },
        quitar(id) { this.lista = this.lista.filter((a) => a.id !== id); },
    });

    window.Alpine.data('dinero', () => ({
        bs(valor) {
            return 'Bs ' + Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    }));
});

window.addEventListener('aviso', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    window.Alpine?.store('avisos')?.agregar(d.tipo || 'info', d.mensaje || '');
});

// ---------- Trazabilidad: latido ----------
(function () {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const INTERVALO = 60000;

    function enviar(ruta, datos, conBeacon) {
        const cuerpo = new FormData();
        cuerpo.append('_token', csrf());
        Object.entries(datos || {}).forEach(([k, v]) => { if (v !== null && v !== undefined) cuerpo.append(k, v); });

        // sendBeacon sobrevive al cierre de la pestaña; fetch no siempre
        if (conBeacon && navigator.sendBeacon) {
            try { navigator.sendBeacon(ruta, cuerpo); return; } catch {}
        }

        fetch(ruta, { method: 'POST', body: cuerpo, credentials: 'same-origin', keepalive: true, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
    }

    if (!document.querySelector('meta[name="usuario"]')) return;

    const reloj = setInterval(() => { if (document.visibilityState === 'visible') enviar('/trazabilidad/latido', {}); }, INTERVALO);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') enviar('/trazabilidad/latido', {}, true); });
    window.addEventListener('pagehide', () => enviar('/trazabilidad/latido', {}, true));
    window.addEventListener('beforeunload', () => clearInterval(reloj));

    // Eventos que el usuario hace fuera de Livewire (imprimir, abrir comprobante)
    window.rastro = (accion, descripcion, extra = {}) => enviar('/trazabilidad/evento', { accion, descripcion, ...extra }, true);

    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('a[data-rastro]');
        if (enlace) window.rastro(enlace.dataset.rastroAccion || 'ver', enlace.dataset.rastro, { referencia: enlace.dataset.rastroRef || null, url: enlace.href.slice(0, 500) });
    });
})();

// ---------- Atajo de busqueda ----------
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('abrir-buscador'));
    }
});
