const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.site-nav');
const header = document.querySelector('.site-header');
const langButton = document.querySelector('.lang-toggle');
const flowTabs = Array.from(document.querySelectorAll('.flow-tab'));
const flowPanels = Array.from(document.querySelectorAll('.flow-panel'));
const fieldNotesSection = document.querySelector('[data-feed]');
const fieldNotesNavigation = document.querySelector('.field-notes-nav');
const photoGallery = document.querySelector('[data-photo-gallery]');
const galleryCount = document.querySelector('[data-gallery-count]');
const photoLightbox = document.querySelector('[data-photo-lightbox]');
const lightboxImage = document.querySelector('[data-photo-lightbox-image]');
const lightboxTitle = document.querySelector('[data-photo-lightbox-title]');
const lightboxCaption = document.querySelector('[data-photo-lightbox-caption]');
const lightboxMeta = document.querySelector('[data-photo-lightbox-meta]');

function setNavigationState(isOpen) {
  nav?.classList.toggle('open', isOpen);
  navToggle?.setAttribute('aria-expanded', String(isOpen));
  const isArabic = document.documentElement.lang === 'ar';
  navToggle?.setAttribute('aria-label', isOpen
    ? (isArabic ? 'إغلاق قائمة التنقل' : 'Close navigation')
    : (isArabic ? 'فتح قائمة التنقل' : 'Open navigation'));
}

navToggle?.addEventListener('click', () => {
  setNavigationState(!nav.classList.contains('open'));
});

document.querySelectorAll('.site-nav a').forEach((link) => {
  link.addEventListener('click', () => {
    setNavigationState(false);
  });
});

const sectionLinks = Array.from(document.querySelectorAll('.site-nav a[href^="#"]'))
  .filter((link) => link.getAttribute('href') !== '#contact');
const observedSections = sectionLinks
  .map((link) => document.querySelector(link.getAttribute('href')))
  .filter(Boolean);

if ('IntersectionObserver' in window && observedSections.length) {
  const navigationObserver = new IntersectionObserver((entries) => {
    const current = entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

    if (!current) return;
    sectionLinks.forEach((link) => {
      const isActive = link.getAttribute('href') === `#${current.target.id}`;
      link.classList.toggle('active', isActive);
      if (isActive) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  }, { rootMargin: '-18% 0px -62% 0px', threshold: [0, 0.1, 0.35] });

  observedSections.forEach((section) => navigationObserver.observe(section));
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && nav?.classList.contains('open')) {
    setNavigationState(false);
    navToggle?.focus();
  }
});

document.addEventListener('click', (event) => {
  if (nav?.classList.contains('open') && !header?.contains(event.target)) {
    setNavigationState(false);
  }
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 900 && nav?.classList.contains('open')) {
    setNavigationState(false);
  }
}, { passive: true });

window.addEventListener('scroll', () => {
  header?.classList.toggle('scrolled', window.scrollY > 8);
}, { passive: true });

const year = document.getElementById('year');
if (year) year.textContent = new Date().getFullYear();

const revealItems = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -45px' });

  revealItems.forEach((item) => observer.observe(item));
} else {
  revealItems.forEach((item) => item.classList.add('visible'));
}

let activeLanguage = localStorage.getItem('anas-site-lang') || 'en';
let galleryItems = [];

function formatGalleryDate(value) {
  if (!value) return '';
  const date = new Date(`${value}T00:00:00Z`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(activeLanguage === 'ar' ? 'ar-SY' : 'en-GB', {
    year: 'numeric',
    month: 'long',
    timeZone: 'UTC'
  }).format(date);
}

function galleryText(item, field) {
  return item[`${field}_${activeLanguage}`] || item[`${field}_ar`] || '';
}

function galleryContext(item) {
  return [formatGalleryDate(item.captured_on), item.project_name, item.organization_name, item.location]
    .filter(Boolean);
}

function openPhotoLightbox(item) {
  if (!photoLightbox || !lightboxImage || !lightboxTitle || !lightboxCaption || !lightboxMeta) return;
  lightboxImage.src = item.image_url;
  lightboxImage.alt = galleryText(item, 'alt_text');
  lightboxTitle.textContent = galleryText(item, 'title');
  lightboxCaption.textContent = galleryText(item, 'caption');
  lightboxMeta.replaceChildren(...galleryContext(item).map((value) => {
    const span = document.createElement('span');
    span.textContent = value;
    return span;
  }));
  photoLightbox.querySelector('[data-photo-lightbox-close]')?.setAttribute('aria-label', activeLanguage === 'ar' ? 'إغلاق الصورة' : 'Close image');
  if (typeof photoLightbox.showModal === 'function') photoLightbox.showModal();
  else photoLightbox.setAttribute('open', '');
}

function renderGallery() {
  if (!photoGallery || !galleryItems.length) return;
  photoGallery.replaceChildren();

  const secondaryCount = Math.max(0, galleryItems.length - 1);
  const secondaryRemainder = secondaryCount % 3;
  const balancedTailStart = secondaryCount === 1
    ? 1
    : secondaryRemainder === 2
      ? galleryItems.length - 2
      : secondaryRemainder === 1 && secondaryCount >= 4
        ? galleryItems.length - 4
        : Number.POSITIVE_INFINITY;

  galleryItems.forEach((item, index) => {
    const isVideo = item.type === 'video';
    const figure = document.createElement('figure');
    figure.className = 'field-note';
    if (index === 0) figure.classList.add('field-note-featured');
    else if (secondaryCount === 1) figure.classList.add('field-note-solo');
    else if (index >= balancedTailStart) figure.classList.add('field-note-wide');
    if (isVideo) figure.classList.add('field-note-has-video');
    if (Number(item.height) > Number(item.width) * 1.1) figure.classList.add('field-note-portrait');

    let media;
    if (isVideo) {
      const video = document.createElement('video');
      video.className = 'field-note-video';
      video.controls = true;
      video.preload = 'metadata';
      video.playsInline = true;
      video.poster = item.poster_url || '';
      video.setAttribute('aria-label', `${activeLanguage === 'ar' ? 'تشغيل الفيديو:' : 'Play video:'} ${galleryText(item, 'title')}`);
      const source = document.createElement('source');
      source.src = item.media_url;
      video.append(source);
      media = video;
    } else {
      const button = document.createElement('button');
      button.className = 'field-note-image';
      button.type = 'button';
      button.setAttribute('aria-label', `${activeLanguage === 'ar' ? 'عرض الصورة:' : 'View image:'} ${galleryText(item, 'title')}`);
      button.addEventListener('click', () => openPhotoLightbox(item));
      const image = document.createElement('img');
      image.src = item.image_url;
      image.alt = galleryText(item, 'alt_text');
      image.loading = index < 2 ? 'eager' : 'lazy';
      image.decoding = 'async';
      if (item.width) image.width = item.width;
      if (item.height) image.height = item.height;
      button.append(image);
      media = button;
    }

    const caption = document.createElement('figcaption');
    const meta = document.createElement('div');
    meta.className = 'field-note-meta';
    galleryContext(item).forEach((value) => {
      const span = document.createElement('span');
      span.textContent = value;
      meta.append(span);
    });
    const title = document.createElement('h3');
    title.textContent = galleryText(item, 'title');
    const copy = document.createElement('p');
    copy.textContent = galleryText(item, 'caption');
    caption.append(meta, title, copy);
    if (item.people_context) {
      const people = document.createElement('small');
      people.textContent = item.people_context;
      caption.append(people);
    }
    figure.append(media, caption);
    photoGallery.append(figure);
  });

  if (galleryCount) {
    const videoCount = galleryItems.filter((item) => item.type === 'video').length;
    const imageCount = galleryItems.length - videoCount;
    const parts = [];
    if (imageCount) parts.push(activeLanguage === 'ar' ? `${imageCount} صورة` : `${imageCount} ${imageCount === 1 ? 'image' : 'images'}`);
    if (videoCount) parts.push(activeLanguage === 'ar' ? `${videoCount} فيديو` : `${videoCount} ${videoCount === 1 ? 'video' : 'videos'}`);
    galleryCount.textContent = parts.join(' · ');
  }
}

async function loadGallery() {
  if (!fieldNotesSection || !photoGallery) return;
  try {
    const response = await fetch(fieldNotesSection.dataset.feed, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const payload = await response.json();
    galleryItems = Array.isArray(payload.items) ? payload.items : [];
    if (!galleryItems.length) return;
    fieldNotesSection.hidden = false;
    if (fieldNotesNavigation) fieldNotesNavigation.hidden = false;
    renderGallery();
  } catch (_) {
    // The portfolio remains complete when the optional album feed is unavailable.
  }
}

document.querySelector('[data-photo-lightbox-close]')?.addEventListener('click', () => photoLightbox?.close());
photoLightbox?.addEventListener('click', (event) => {
  if (event.target === photoLightbox) photoLightbox.close();
});

function activateFlow(flowName, moveFocus = false) {
  flowTabs.forEach((tab) => {
    const isActive = tab.dataset.flow === flowName;
    tab.classList.toggle('active', isActive);
    tab.setAttribute('aria-selected', String(isActive));
    tab.tabIndex = isActive ? 0 : -1;
    if (isActive && moveFocus) tab.focus();
  });

  flowPanels.forEach((panel) => {
    const isActive = panel.id === `flow-panel-${flowName}`;
    panel.classList.toggle('active', isActive);
    panel.hidden = !isActive;
  });
}

flowTabs.forEach((tab, index) => {
  tab.addEventListener('click', () => activateFlow(tab.dataset.flow));
  tab.addEventListener('keydown', (event) => {
    if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();

    let nextIndex = index;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = flowTabs.length - 1;
    if (event.key === 'ArrowRight') nextIndex = (index + 1) % flowTabs.length;
    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + flowTabs.length) % flowTabs.length;
    activateFlow(flowTabs[nextIndex].dataset.flow, true);
  });
});

function applyLanguage(language) {
  activeLanguage = language === 'ar' ? 'ar' : 'en';
  const isArabic = activeLanguage === 'ar';

  document.documentElement.lang = activeLanguage;
  document.documentElement.dir = isArabic ? 'rtl' : 'ltr';
  document.title = isArabic
    ? 'أنس النجار — إدارة المعلومات وأنظمة البيانات التشغيلية'
    : 'Anas Al-Najjar — Information Management & Operational Data Systems';

  const description = document.querySelector('meta[name="description"]');
  description?.setAttribute('content', isArabic
    ? 'يصمّم أنس النجار أنظمة بيانات تشغيلية تربط الجمع الميداني وجودة البيانات والأتمتة والتقارير ودعم القرار.'
    : 'Anas Al-Najjar designs operational data systems that connect field collection, data quality, automation, reporting and decision support.');

  document.querySelectorAll('[data-en][data-ar]').forEach((element) => {
    element.textContent = element.dataset[activeLanguage];
  });

  if (langButton) {
    langButton.textContent = isArabic ? 'English' : 'العربية';
    langButton.setAttribute('aria-label', isArabic ? 'Switch to English' : 'التبديل إلى العربية');
  }

  setNavigationState(nav?.classList.contains('open') || false);

  if (galleryItems.length) renderGallery();

  localStorage.setItem('anas-site-lang', activeLanguage);
}

langButton?.addEventListener('click', () => {
  setNavigationState(false);
  document.body.style.opacity = '0.72';
  window.setTimeout(() => {
    applyLanguage(activeLanguage === 'en' ? 'ar' : 'en');
    document.body.style.opacity = '1';
  }, 120);
});

applyLanguage(activeLanguage);
activateFlow(document.querySelector('.flow-tab.active')?.dataset.flow || 'collect');
loadGallery();
