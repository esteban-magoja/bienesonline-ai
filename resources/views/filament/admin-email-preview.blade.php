{{-- Estilos inline: las clases de Tailwind propias no están en el CSS compilado de Filament --}}
<div>
    <p style="margin-bottom: 0.75rem; font-size: 0.875rem; opacity: 0.8;">
        Asunto: <strong>{{ $subject }}</strong>
    </p>
    <iframe srcdoc="{{ $html }}" sandbox
            style="width: 100%; height: 60vh; border: 1px solid rgba(127, 127, 127, 0.3); border-radius: 0.5rem; background: #ffffff;"></iframe>
</div>
