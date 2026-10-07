const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.site-nav');
const header = document.querySelector('.site-header');
const langButton = document.querySelector('.lang-toggle');

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

function applyLanguage(language) {
  activeLanguage = language === 'ar' ? 'ar' : 'en';
  const isArabic = activeLanguage === 'ar';

  document.documentElement.lang = activeLanguage;
  document.documentElement.dir = isArabic ? 'rtl' : 'ltr';

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
  document.body.style.opacity = '0.72';
  window.setTimeout(() => {
    applyLanguage(activeLanguage === 'en' ? 'ar' : 'en');
    document.body.style.opacity = '1';
  }, 120);
});

applyLanguage(activeLanguage);
