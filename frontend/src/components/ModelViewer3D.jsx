/**
 * ModelViewer3D - Wrapper around Google's <model-viewer> web component
 * Handles lazy loading, error states, and AR support
 */
export default function ModelViewer3D({
  src,
  alt = 'Sneaker 3D Model',
  className = '',
  autoRotate = true,
  cameraControls = true,
  ar = false,
  poster = null,
  minHeight = '400px',
}) {
  if (!src) {
    return (
      <div
        className={`flex items-center justify-center bg-white/3 rounded-2xl ${className}`}
        style={{ minHeight }}
      >
        <div className="text-center">
          <div className="w-16 h-16 mx-auto mb-3 bg-white/10 rounded-full flex items-center justify-center">
            <svg className="w-8 h-8 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <p className="text-white/30 text-sm">Modelo 3D no disponible</p>
        </div>
      </div>
    );
  }

  return (
    <model-viewer
      src={src}
      alt={alt}
      auto-rotate={autoRotate ? '' : undefined}
      camera-controls={cameraControls ? '' : undefined}
      ar={ar ? '' : undefined}
      ar-modes={ar ? 'webxr scene-viewer quick-look' : undefined}
      poster={poster}
      shadow-intensity="1"
      shadow-softness="1"
      environment-image="neutral"
      exposure="0.8"
      class={`block w-full h-full ${className}`}
      style={{ minHeight, background: 'transparent' }}
      loading="eager"
    >
      <div slot="poster" className="w-full h-full flex items-center justify-center">
        <div className="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin" />
      </div>
    </model-viewer>
  );
}
