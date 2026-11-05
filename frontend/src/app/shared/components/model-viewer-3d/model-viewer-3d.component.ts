import {
  Component,
  Input,
  ElementRef,
  ViewChild,
  CUSTOM_ELEMENTS_SCHEMA,
  signal,
  OnChanges,
  SimpleChanges
} from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-model-viewer-3d',
  standalone: true,
  imports: [CommonModule],
  schemas: [CUSTOM_ELEMENTS_SCHEMA],
  template: `
    <div class="viewer-3d-shell" [class.fullscreen-active]="isFullscreen()" [style.minHeight]="minHeight" #shellRef>
      <!-- Ambient background glow -->
      <div class="viewer-ambient-glow" [attr.data-light]="lightingMode()"></div>
      <div class="viewer-grid-floor"></div>

      <!-- Top Floating HUD -->
      <div class="viewer-hud-top" *ngIf="showControls">
        <div class="badge-3d-live">
          <span class="pulse-dot"></span>
          <span>VISOR 3D · 360° REAL</span>
        </div>

        <div class="hud-controls">
          <button
            type="button"
            class="hud-btn"
            [class.active]="isAutoRotate()"
            (click)="toggleAutoRotate()"
            title="Activar/Pausar rotación automática"
          >
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hud-icon">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span class="hud-btn-label">{{ isAutoRotate() ? 'Giro Auto' : 'Pausado' }}</span>
          </button>

          <button
            type="button"
            class="hud-btn"
            (click)="cycleLighting()"
            title="Cambiar iluminación del estudio 3D"
          >
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hud-icon">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <span class="hud-btn-label">{{ lightingLabel }}</span>
          </button>

          <button
            type="button"
            class="hud-btn"
            (click)="resetCamera()"
            title="Restablecer ángulo de cámara"
          >
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hud-icon">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span class="hud-btn-label">Centrar</span>
          </button>

          <button
            type="button"
            class="hud-btn icon-only"
            (click)="toggleFullscreen()"
            title="Pantalla completa"
          >
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hud-icon">
              <path *ngIf="!isFullscreen()" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
              <path *ngIf="isFullscreen()" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Model Viewer Element -->
      <model-viewer
        *ngIf="src; else noModelTpl"
        #modelViewerEl
        [attr.src]="src"
        [attr.alt]="alt"
        [attr.poster]="poster || null"
        [attr.auto-rotate]="isAutoRotate() ? '' : null"
        auto-rotate-delay="0"
        rotation-per-second="28deg"
        camera-controls
        touch-action="pan-y"
        interaction-prompt="none"
        [attr.shadow-intensity]="shadowIntensity"
        shadow-softness="0.85"
        environment-image="neutral"
        [attr.exposure]="exposureValue"
        camera-orbit="35deg 75deg 105%"
        min-camera-orbit="auto auto 60%"
        max-camera-orbit="auto auto 180%"
        loading="eager"
        (load)="onModelLoad()"
        (error)="onModelError()"
        class="model-viewer-canvas"
        [style.minHeight]="minHeight"
      >
        <div slot="poster" class="viewer-loading-overlay">
          <div class="spinner-3d"></div>
          <p class="loading-text">Cargando modelo 3D interactivo...</p>
        </div>
      </model-viewer>

      <!-- Loading state when switching models -->
      <div *ngIf="isLoading() && src" class="viewer-loading-badge">
        <div class="spinner-mini"></div>
        <span>Renderizando malla 3D...</span>
      </div>

      <!-- Fallback if no src -->
      <ng-template #noModelTpl>
        <div class="no-model-fallback" [style.minHeight]="minHeight">
          <p>Modelo 3D no disponible</p>
        </div>
      </ng-template>

      <!-- Bottom Hint Bar -->
      <div class="viewer-hud-bottom" *ngIf="showHint">
        <div class="hint-pill">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hint-icon">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
          </svg>
          <span>Arrastrá para girar 360° · Rueda o pellizcá para hacer zoom</span>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .viewer-3d-shell {
      position: relative;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 50% 42%, #1d2028 0%, #0f1014 68%, #09090b 100%);
      border-radius: 1.5rem;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.1);
      user-select: none;
    }

    .viewer-3d-shell.fullscreen-active {
      position: fixed;
      inset: 0;
      z-index: 9999;
      border-radius: 0;
      min-height: 100vh !important;
    }

    .viewer-ambient-glow {
      position: absolute;
      width: 75%;
      height: 75%;
      border-radius: 9999px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(56, 189, 248, 0.03) 45%, transparent 70%);
      filter: blur(28px);
      pointer-events: none;
      transition: all 0.4s ease;
    }
    .viewer-ambient-glow[data-light="warm"] {
      background: radial-gradient(circle, rgba(251, 146, 60, 0.14) 0%, rgba(239, 68, 68, 0.05) 45%, transparent 70%);
    }
    .viewer-ambient-glow[data-light="vivid"] {
      background: radial-gradient(circle, rgba(56, 189, 248, 0.16) 0%, rgba(168, 85, 247, 0.07) 45%, transparent 70%);
    }

    .viewer-grid-floor {
      position: absolute;
      inset: 0;
      opacity: 0.04;
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.6) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.6) 1px, transparent 1px);
      background-size: 36px 36px;
      pointer-events: none;
    }

    .model-viewer-canvas {
      width: 100%;
      height: 100%;
      display: block;
      background: transparent;
      outline: none;
      cursor: grab;
      z-index: 2;
    }
    .model-viewer-canvas:active {
      cursor: grabbing;
    }

    .viewer-hud-top {
      position: absolute;
      top: 0.85rem;
      left: 0.85rem;
      right: 0.85rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      z-index: 10;
      flex-wrap: wrap;
      pointer-events: none;
    }

    .badge-3d-live {
      pointer-events: auto;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: rgba(10, 10, 14, 0.78);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.14);
      padding: 0.35rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      color: #ffffff;
    }

    .pulse-dot {
      width: 7px;
      height: 7px;
      border-radius: 9999px;
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
    }

    .hud-controls {
      pointer-events: auto;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      flex-wrap: wrap;
    }

    .hud-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: rgba(15, 15, 20, 0.78);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: rgba(255, 255, 255, 0.78);
      padding: 0.35rem 0.65rem;
      border-radius: 0.65rem;
      font-size: 0.72rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.18s ease;
    }
    .hud-btn:hover {
      background: rgba(255, 255, 255, 0.14);
      color: #ffffff;
      border-color: rgba(255, 255, 255, 0.28);
    }
    .hud-btn.active {
      background: rgba(255, 255, 255, 0.92);
      color: #09090b;
      border-color: #ffffff;
      font-weight: 700;
    }
    .hud-btn.icon-only {
      padding: 0.35rem 0.45rem;
    }

    .hud-icon {
      width: 14px;
      height: 14px;
      flex-shrink: 0;
    }

    @media (max-width: 640px) {
      .hud-btn-label {
        display: none;
      }
    }

    .viewer-hud-bottom {
      position: absolute;
      bottom: 0.85rem;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10;
      pointer-events: none;
      width: max-content;
      max-width: 92%;
    }

    .hint-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: rgba(10, 10, 14, 0.76);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 0.4rem 0.9rem;
      border-radius: 9999px;
      color: rgba(255, 255, 255, 0.65);
      font-size: 0.72rem;
      font-weight: 500;
    }

    .hint-icon {
      width: 14px;
      height: 14px;
      color: rgba(255, 255, 255, 0.8);
      flex-shrink: 0;
    }

    .viewer-loading-overlay {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      background: rgba(12, 12, 16, 0.6);
    }

    .viewer-loading-badge {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 15;
      background: rgba(10, 10, 14, 0.88);
      border: 1px solid rgba(255, 255, 255, 0.15);
      padding: 0.65rem 1.1rem;
      border-radius: 0.85rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 0.8rem;
      color: #ffffff;
      pointer-events: none;
    }

    .spinner-3d {
      width: 34px;
      height: 34px;
      border: 3px solid rgba(255, 255, 255, 0.15);
      border-top-color: #ffffff;
      border-radius: 9999px;
      animation: spin3d 0.8s linear infinite;
    }

    .spinner-mini {
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255, 255, 255, 0.2);
      border-top-color: #ffffff;
      border-radius: 9999px;
      animation: spin3d 0.7s linear infinite;
    }

    @keyframes spin3d {
      to { transform: rotate(360deg); }
    }

    .loading-text {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.6);
    }

    .no-model-fallback {
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255, 255, 255, 0.4);
      font-size: 0.875rem;
    }
  `]
})
export class ModelViewer3dComponent implements OnChanges {
  @Input() src: string | null | undefined = '';
  @Input() alt = 'Modelo 3D de Zapatilla';
  @Input() poster: string | null | undefined = null;
  @Input() autoRotate = true;
  @Input() minHeight = '440px';
  @Input() showControls = true;
  @Input() showHint = true;

  @ViewChild('modelViewerEl') modelViewerEl?: ElementRef<any>;
  @ViewChild('shellRef') shellRef?: ElementRef<HTMLElement>;

  isAutoRotate = signal<boolean>(true);
  lightingMode = signal<'studio' | 'vivid' | 'warm'>('studio');
  isFullscreen = signal<boolean>(false);
  isLoading = signal<boolean>(false);

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['autoRotate']) {
      this.isAutoRotate.set(this.autoRotate);
    }
    if (changes['src'] && !changes['src'].firstChange) {
      this.isLoading.set(true);
    }
  }

  get lightingLabel(): string {
    const mode = this.lightingMode();
    if (mode === 'vivid') return 'Luz Neón';
    if (mode === 'warm') return 'Luz Cálida';
    return 'Luz Estudio';
  }

  get exposureValue(): string {
    const mode = this.lightingMode();
    if (mode === 'vivid') return '1.15';
    if (mode === 'warm') return '1.05';
    return '0.95';
  }

  get shadowIntensity(): string {
    const mode = this.lightingMode();
    if (mode === 'vivid') return '1.3';
    if (mode === 'warm') return '1.1';
    return '1.0';
  }

  toggleAutoRotate(): void {
    this.isAutoRotate.update(v => !v);
  }

  cycleLighting(): void {
    const order: ('studio' | 'vivid' | 'warm')[] = ['studio', 'vivid', 'warm'];
    const next = order[(order.indexOf(this.lightingMode()) + 1) % order.length];
    this.lightingMode.set(next);
  }

  resetCamera(): void {
    const el = this.modelViewerEl?.nativeElement;
    if (el) {
      el.cameraOrbit = '35deg 75deg 105%';
      el.fieldOfView = 'auto';
    }
  }

  toggleFullscreen(): void {
    this.isFullscreen.update(v => !v);
  }

  onModelLoad(): void {
    this.isLoading.set(false);
  }

  onModelError(): void {
    this.isLoading.set(false);
  }
}
