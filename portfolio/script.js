const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.site-nav');
const header = document.querySelector('.site-header');
const langButton = document.querySelector('.lang-toggle');
const flowTabs = Array.from(document.querySelectorAll('.flow-tab'));
const flowPanels = Array.from(document.querySelectorAll('.flow-panel'));

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
