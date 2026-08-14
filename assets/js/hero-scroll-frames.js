document.addEventListener('DOMContentLoaded', () => {
  const heroSection = document.getElementById('hero-scroll-section');
  const canvas = document.getElementById('heroFrameCanvas');
  const fallbackImage = document.getElementById('heroFrameFallback');

  if (!heroSection || !canvas || !fallbackImage) {
    return;
  }

  const FRAME_COUNT = 80;
  const FRAME_DIR = 'assets/hero-frames-transparent';
  // Compress the scrub window so the sequence finishes before the hero fully exits.
  const SCRUB_DISTANCE = 0.65;
  const FRAME_PATHS = Array.from(
    { length: FRAME_COUNT },
    (_, index) => `${FRAME_DIR}/frame_${String(index + 1).padStart(3, '0')}.png`
  );

  const frames = new Array(FRAME_COUNT);
  const ctx = canvas.getContext('2d', { alpha: true });

  if (!ctx) {
    return;
  }

  let loaded = false;
  let loadFailed = false;
  let naturalWidth = 0;
  let naturalHeight = 0;
  let currentFrame = -1;
  let targetFrame = 0;
  let rafPending = false;

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function getScrollProgress() {
    const rect = heroSection.getBoundingClientRect();
    const height = Math.max(rect.height * SCRUB_DISTANCE, 1);

    // 0 when the section top reaches the viewport top, 1 after the section has fully passed.
    return clamp((-rect.top) / height, 0, 1);
  }

  function setCanvasResolution() {
    if (!naturalWidth || !naturalHeight) {
      return;
    }

    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.round(naturalWidth * dpr);
    canvas.height = Math.round(naturalHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function drawFrame(index) {
    if (!loaded || loadFailed) {
      return;
    }

    const frame = frames[index] || frames[0];
    if (!frame) {
      return;
    }

    if (!naturalWidth || !naturalHeight) {
      naturalWidth = frame.naturalWidth || frame.width;
      naturalHeight = frame.naturalHeight || frame.height;
      setCanvasResolution();
    }

    ctx.clearRect(0, 0, naturalWidth, naturalHeight);
    ctx.drawImage(frame, 0, 0, naturalWidth, naturalHeight);
    currentFrame = index;
  }

  function renderTargetFrame() {
    rafPending = false;

    if (!loaded || loadFailed) {
      return;
    }

    targetFrame = Math.floor(getScrollProgress() * (FRAME_COUNT - 1));
    targetFrame = clamp(targetFrame, 0, FRAME_COUNT - 1);

    if (targetFrame !== currentFrame) {
      drawFrame(targetFrame);
    }
  }

  function requestRender() {
    if (rafPending) {
      return;
    }

    rafPending = true;
    window.requestAnimationFrame(renderTargetFrame);
  }

  function showFallback() {
    loadFailed = true;
    canvas.style.opacity = '0';
    fallbackImage.classList.remove('invisible');
  }

  function showCanvas() {
    canvas.style.opacity = '1';
    fallbackImage.classList.add('invisible');
  }

  function preloadFrames() {
    return Promise.all(
      FRAME_PATHS.map((src, index) => new Promise((resolve, reject) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve({ img, index });
        img.onerror = reject;
        img.src = src;
      }))
    );
  }

  preloadFrames()
    .then((results) => {
      results.forEach(({ img, index }) => {
        frames[index] = img;
      });

      const firstFrame = frames[0];
      if (!firstFrame) {
        showFallback();
        return;
      }

      naturalWidth = firstFrame.naturalWidth || firstFrame.width;
      naturalHeight = firstFrame.naturalHeight || firstFrame.height;

      if (!naturalWidth || !naturalHeight) {
        showFallback();
        return;
      }

      setCanvasResolution();
      loaded = true;
      drawFrame(0);
      showCanvas();
      requestRender();
    })
    .catch(() => {
      showFallback();
    });

  window.addEventListener('scroll', requestRender, { passive: true });
  window.addEventListener('resize', () => {
    if (loaded && !loadFailed) {
      setCanvasResolution();
      requestRender();
    }
  });
});
