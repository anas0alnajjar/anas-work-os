const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.site-nav');
const header = document.querySelector('.site-header');
const langButton = document.querySelector('.lang-toggle');

navToggle?.addEventListener('click', () => {
  const isOpen = nav.classList.toggle('open');
  navToggle.setAttribute('aria-expanded', String(isOpen));
  navToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
});

document.querySelectorAll('.site-nav a').forEach((link) => {
  link.addEventListener('click', () => {
    nav.classList.remove('open');
    navToggle?.setAttribute('aria-expanded', 'false');
  });
});

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
