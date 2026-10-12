document.addEventListener('DOMContentLoaded', () => {
  initThemeSwitcher();
  initHamburgerMenu();
  initNotificationBanner();
  initModalPopup();
  initContentSlider();
  initProfileManagement();
  initRegisterValidation();
});

function initThemeSwitcher() {
  const themeToggleButtons = document.querySelectorAll('.theme-toggle-btn');
  const storedTheme = localStorage.getItem('studenthub_theme');
 
  if (storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.body.classList.add('dark-theme');
    document.documentElement.classList.add('dark');
    updateToggleButtons(true);
  } else {
    document.body.classList.remove('dark-theme');
    document.documentElement.classList.remove('dark');
    updateToggleButtons(false);
  }

  themeToggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const isDark = document.body.classList.toggle('dark-theme');
      document.documentElement.classList.toggle('dark', isDark);
      localStorage.setItem('studenthub_theme', isDark ? 'dark' : 'light');
      updateToggleButtons(isDark);
    });
  });

  function updateToggleButtons(isDark) {
    themeToggleButtons.forEach(btn => {
      const icon = btn.querySelector('.theme-icon');
      const text = btn.querySelector('.theme-text');
      if (icon) icon.textContent = isDark ? '☀️' : '🌙';
      if (text) text.textContent = isDark ? 'Light' : 'Dark';
      btn.setAttribute('aria-label', isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme');
    });
  }
}

function initHamburgerMenu() {
  const hamburgerBtn = document.querySelector('.hamburger-btn');
  const navMenu = document.querySelector('header nav');

  if (!hamburgerBtn || !navMenu) return;

  hamburgerBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const expanded = hamburgerBtn.getAttribute('aria-expanded') === 'true';
    hamburgerBtn.setAttribute('aria-expanded', !expanded);
    hamburgerBtn.classList.toggle('is-active');
    navMenu.classList.toggle('nav-open');
  });

  document.addEventListener('click', (e) => {
    if (navMenu.classList.contains('nav-open') && !navMenu.contains(e.target) && !hamburgerBtn.contains(e.target)) {
      hamburgerBtn.setAttribute('aria-expanded', 'false');
      hamburgerBtn.classList.remove('is-active');
      navMenu.classList.remove('nav-open');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && navMenu.classList.contains('nav-open')) {
      hamburgerBtn.setAttribute('aria-expanded', 'false');
      hamburgerBtn.classList.remove('is-active');
      navMenu.classList.remove('nav-open');
    }
  });
}

function initNotificationBanner() {
  const banner = document.getElementById('notification-banner');
  if (!banner) return;

  const dismissBtn = banner.querySelector('.banner-dismiss');
  const bannerId = banner.getAttribute('data-banner-id') || 'studenthub_banner_v1';

  if (sessionStorage.getItem(bannerId) === 'dismissed') {
    banner.style.display = 'none';
    return;
  }

  if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
      banner.classList.add('banner-hiding');
      setTimeout(() => {
        banner.style.display = 'none';
        banner.classList.remove('banner-hiding');
        sessionStorage.setItem(bannerId, 'dismissed');
      }, 300);
    });
  }
}

function initModalPopup() {
  const modal = document.getElementById('global-modal');
  if (!modal) return;

  const modalTitle = modal.querySelector('.modal-title');
  const modalBody = modal.querySelector('.modal-body');
  const closeBtns = modal.querySelectorAll('[data-dismiss="modal"]');

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-modal-target="#global-modal"], [data-open-modal]');
    if (!trigger) return;

    e.preventDefault();
    const title = trigger.getAttribute('data-modal-title') || 'Information';
    const content = trigger.getAttribute('data-modal-content') || trigger.nextElementSibling?.innerHTML || '';
    
    if (modalTitle) modalTitle.textContent = title;
    if (modalBody && content) {
      modalBody.innerHTML = content;
    }
    
    openModal(modal);
  });

  closeBtns.forEach(btn => {
    btn.addEventListener('click', () => closeModal(modal));
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal(modal);
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeModal(modal);
    }
  });

  function openModal(modalEl) {
    modalEl.classList.add('active');
    modalEl.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden'; 
    const focusable = modalEl.querySelector('button, [href], input, select, textarea');
    if (focusable) focusable.focus();
  }

  function closeModal(modalEl) {
    modalEl.classList.remove('active');
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

window.openStudentHubModal = function(title, HTMLContent) {
  const modal = document.getElementById('global-modal');
  if (!modal) return;
  const modalTitle = modal.querySelector('.modal-title');
  const modalBody = modal.querySelector('.modal-body');
  if (modalTitle) modalTitle.textContent = title;
  if (modalBody) modalBody.innerHTML = HTMLContent;
  modal.classList.add('active');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
};
function initContentSlider() {
  const slider = document.querySelector('.content-slider');
  if (!slider) return;

  const track = slider.querySelector('.slider-track');
  const slides = Array.from(slider.querySelectorAll('.slide'));
  const prevBtn = slider.querySelector('.slider-prev');
  const nextBtn = slider.querySelector('.slider-next');
  const dotsContainer = slider.querySelector('.slider-dots');

  if (!slides.length) return;

  let currentIndex = 0;
  let autoPlayInterval = null;
  const autoPlayDelay = 5000;

  if (dotsContainer && dotsContainer.children.length === 0) {
    dotsContainer.innerHTML = '';
    slides.forEach((_, idx) => {
      const dot = document.createElement('button');
      dot.classList.add('slider-dot');
      if (idx === 0) dot.classList.add('active');
      dot.setAttribute('aria-label', `Go to slide ${idx + 1}`);
      dot.addEventListener('click', () => goToSlide(idx));
      dotsContainer.appendChild(dot);
    });
  }

  const dots = dotsContainer ? Array.from(dotsContainer.querySelectorAll('.slider-dot')) : [];
  if (dots.length > 0) {
    dots.forEach((dot, idx) => {
      dot.addEventListener('click', () => goToSlide(idx));
    });
  }

  function updateSlider() {
    slides.forEach((slide, idx) => {
      slide.classList.toggle('active', idx === currentIndex);
    });
    if (track) {
      track.style.transform = `translateX(-${currentIndex * 100}%)`;
    }
    dots.forEach((dot, idx) => {
      dot.classList.toggle('active', idx === currentIndex);
    });
  }

  function goToSlide(index) {
    currentIndex = (index + slides.length) % slides.length;
    updateSlider();
    resetAutoPlay();
  }

  function nextSlide() {
    goToSlide(currentIndex + 1);
  }

  function prevSlide() {
    goToSlide(currentIndex - 1);
  }

  if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); });
  if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); });

  slider.setAttribute('tabindex', '0');
  slider.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') nextSlide();
    if (e.key === 'ArrowLeft') prevSlide();
  });

  let startX = 0;
  let currentX = 0;

  slider.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
  }, { passive: true });

  slider.addEventListener('touchmove', (e) => {
    currentX = e.touches[0].clientX;
  }, { passive: true });

  slider.addEventListener('touchend', () => {
    const diffX = startX - currentX;
    if (Math.abs(diffX) > 50 && currentX !== 0) {
      if (diffX > 0) nextSlide();
      else prevSlide();
    }
    startX = 0;
    currentX = 0;
  });

  function startAutoPlay() {
    if (!autoPlayInterval) {
      autoPlayInterval = setInterval(nextSlide, autoPlayDelay);
    }
  }

  function stopAutoPlay() {
    if (autoPlayInterval) {
      clearInterval(autoPlayInterval);
      autoPlayInterval = null;
    }
  }

  function resetAutoPlay() {
    stopAutoPlay();
    startAutoPlay();
  }

  slider.addEventListener('mouseenter', stopAutoPlay);
  slider.addEventListener('mouseleave', startAutoPlay);

  updateSlider();
  startAutoPlay();
}

function initCollapsibleFAQ() {
  const faqAccordion = document.querySelector('.faq-accordion');
  if (!faqAccordion) return;

  const accordionItems = faqAccordion.querySelectorAll('.faq-item');
  const searchInput = document.getElementById('faq-search-input');
  const expandAllBtn = document.getElementById('faq-expand-all');
  const collapseAllBtn = document.getElementById('faq-collapse-all');

  accordionItems.forEach(item => {
    const header = item.querySelector('.faq-question');

    if (!header) return;

    header.addEventListener('click', () => {
      const isOpen = item.classList.contains('active');
      item.classList.toggle('active');
      header.setAttribute('aria-expanded', !isOpen);
    });
  });

  if (expandAllBtn) {
    expandAllBtn.addEventListener('click', () => {
      accordionItems.forEach(item => {
        if (item.style.display !== 'none') {
          item.classList.add('active');
          const header = item.querySelector('.faq-question');
          if (header) header.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  if (collapseAllBtn) {
    collapseAllBtn.addEventListener('click', () => {
      accordionItems.forEach(item => {
        item.classList.remove('active');
        const header = item.querySelector('.faq-question');
        if (header) header.setAttribute('aria-expanded', 'false');
      });
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      let matchCount = 0;

      accordionItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(query)) {
          item.style.display = 'block';
          matchCount++;
          if (query.length > 1) {
            item.classList.add('active');
            const header = item.querySelector('.faq-question');
            if (header) header.setAttribute('aria-expanded', 'true');
          }
        } else {
          item.style.display = 'none';
        }
      });

      const noResultMsg = document.getElementById('faq-no-results');
      if (noResultMsg) {
        noResultMsg.style.display = matchCount === 0 ? 'block' : 'none';
      }
    });
  }
}

function escapeHTML(str) {
  return str.replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

function initRegisterValidation() {
  const form = document.getElementById('register-form');
  if (!form) return;

  const fields = {
    fullname: form.querySelector('#fullname'),
    studentid: form.querySelector('#studentid'),
    email: form.querySelector('#email'),
    password: form.querySelector('#password'),
    confirmPassword: form.querySelector('#confirm-password'),
    course: form.querySelector('#course'),
    year: form.querySelector('#year'),
    department: form.querySelector('#department'),
    phone: form.querySelector('#phone'),
    gender: form.querySelector('#gender-group'),
    terms: form.querySelector('#terms')
  };

  const rules = {
    fullname: {
      test: value => value.trim().length >= 2 && value.trim().length <= 50 && /^[A-Za-z]+(?: [A-Za-z]+)*$/.test(value.trim()),
      message: 'Enter a name of 2–50 letters, using single spaces between names.'
    },
    studentid: {
      test: value => /^[A-Za-z0-9- ]{3,30}$/.test(value.trim()),
      message: 'Enter a valid ID number.'
    },
    email: {
      test: value => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/.test(value.trim()),
      message: 'Enter a valid email address.'
    },
    password: {
      test: value => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,64}$/.test(value),
      message: 'Use 8–64 characters with uppercase and lowercase letters, a number, and a special character.'
    },
    confirmPassword: {
      test: value => value !== '' && value === fields.password.value,
      message: 'Passwords must match.'
    },
    course: {
      test: value => value.trim() !== '',
      message: 'Select your course of study.'
    },
    year: {
      test: value => value.trim() !== '',
      message: 'Select your academic year.'
    },
    department: {
      test: value => value.trim() !== '',
      message: 'Select your department.'
    },
    phone: {
      test: value => /^(?:\+91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/.test(value.trim()),
      message: 'Enter a valid Indian mobile number, for example +91 98765 43210.'
    },
    gender: {
      test: value => value !== '',
      message: 'Select your gender.'
    },
    terms: {
      test: () => fields.terms.checked,
      message: 'You must accept the terms of use.'
    }
  };

  function getErrorElement(element) {
    if (!element) return null;
    const group = element.closest('.form-group, .checkbox-group') || element.parentElement;
    let error = group && group.querySelector('.input-error');
    if (!error && group) {
      error = document.createElement('div');
      error.className = 'input-error';
      group.appendChild(error);
    }
    if (error && !error.id) {
      error.id = `register-error-${element.id || 'gender'}`;
    }
    return error;
  }

  function setError(element, message) {
    if (!element) return;
    const error = getErrorElement(element);
    if (error) error.textContent = message;
    element.classList.add('input-invalid');
    element.setAttribute('aria-invalid', 'true');
    if (error) {
      const descriptions = new Set((element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      descriptions.add(error.id);
      element.setAttribute('aria-describedby', [...descriptions].join(' '));
    }
    element.closest('.form-group, .checkbox-group')?.classList.add('has-error');
  }

  function clearError(element) {
    if (!element) return;
    const error = getErrorElement(element);
    if (error) error.textContent = '';
    element.classList.remove('input-invalid');
    element.setAttribute('aria-invalid', 'false');
    if (error) {
      const descriptions = (element.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter(id => id && id !== error.id);
      if (descriptions.length) {
        element.setAttribute('aria-describedby', descriptions.join(' '));
      } else {
        element.removeAttribute('aria-describedby');
      }
    }
    element.closest('.form-group, .checkbox-group')?.classList.remove('has-error');
  }

  function getFieldValue(fieldName) {
    const field = fields[fieldName];
    if (fieldName === 'gender') {
      return form.querySelector('input[name="gender"]:checked')?.value || '';
    }
    if (field.type === 'checkbox') return field.checked;
    return field.value || '';
  }

  function validateField(fieldName) {
    const field = fields[fieldName];
    const rule = rules[fieldName];
    if (!field || !rule) return true;
    const value = getFieldValue(fieldName);
    const valid = rule.test(value);
    if (!valid) {
      setError(field, rule.message);
    } else {
      clearError(field);
    }
    return valid;
  }

  function displaySummaryMessage(messages) {
    const summary = document.getElementById('register-summary');
    if (!summary) return;
    summary.classList.remove('form-success-summary');
    summary.replaceChildren();
    if (messages.length === 0) {
      summary.style.display = 'none';
      return;
    }
    const heading = document.createElement('strong');
    heading.textContent = 'Please fix the following fields:';
    const list = document.createElement('ul');
    messages.forEach(message => {
      const item = document.createElement('li');
      item.textContent = message;
      list.appendChild(item);
    });
    summary.append(heading, list);
    summary.style.display = 'block';
  }

  function displaySuccessMessage() {
    const summary = document.getElementById('register-summary');
    if (!summary) return;
    summary.replaceChildren();
    summary.classList.add('form-success-summary');
    summary.textContent = 'Validation successful. This demonstration does not send or save your information.';
    summary.style.display = 'block';
  }

  function validateForm() {
    const messages = [];
    Object.keys(rules).forEach(name => {
      if (!validateField(name)) messages.push(rules[name].message);
    });
    displaySummaryMessage(messages);
    return messages.length === 0;
  }

  function resolveFieldName(target) {
    if (!target || !target.id) return null;
    if (target.id === 'confirm-password') return 'confirmPassword';
    if (target.name === 'gender') return 'gender';
    return target.id;
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!validateForm()) {
      const firstInvalid = form.querySelector('.input-invalid');
      const focusTarget = firstInvalid?.id === 'gender-group'
        ? form.querySelector('input[name="gender"]')
        : firstInvalid;
      if (focusTarget) {
        focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        focusTarget.focus();
      }
      return;
    }
    displaySuccessMessage();
  });

  function updatePasswordStrength() {
    const password = fields.password.value;
    const meter = form.querySelector('#password-strength-meter');
    const strengthText = form.querySelector('#password-strength-text');
    const checks = [
      password.length >= 8,
      password.length >= 12,
      /[a-z]/.test(password),
      /[A-Z]/.test(password),
      /\d/.test(password),
      /[^A-Za-z0-9\s]/.test(password)
    ];
    const score = checks.filter(Boolean).length;
    const label = password.length === 0
      ? 'Not entered'
      : score >= 5 ? 'Strong' : score >= 4 ? 'Good' : score >= 3 ? 'Fair' : 'Weak';
    if (meter) meter.value = score;
    if (strengthText) strengthText.textContent = label;
  }

  form.addEventListener('input', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      clearError(fields[fieldName]);
      validateField(fieldName);
    }
    if (target === fields.password) {
      updatePasswordStrength();
      if (fields.confirmPassword.value) validateField('confirmPassword');
    }
  });

  form.addEventListener('change', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      clearError(fields[fieldName]);
      validateField(fieldName);
    }
  });

  form.addEventListener('focusout', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      validateField(fieldName);
    }
  });

  form.addEventListener('reset', () => {
    window.setTimeout(() => {
      Object.values(fields).forEach(clearError);
      displaySummaryMessage([]);
      updatePasswordStrength();
    });
  });

  updatePasswordStrength();
}

function initProfileManagement() {
  const profileForm = document.getElementById('profile-form');
  const displayName = document.getElementById('profile-display-name');
  const displayRoll = document.getElementById('profile-display-roll');
  const nameInput = document.getElementById('prof-fullname');
  const emailInput = document.getElementById('prof-email');
  const phoneInput = document.getElementById('prof-phone');
  const addressInput = document.getElementById('prof-address');
  const emailNotifCheck = document.getElementById('pref-email-notif');
  const eventRemindCheck = document.getElementById('pref-event-remind');
  const statusMsg = document.getElementById('profile-status-msg');

  const activeUser = sessionStorage.getItem('studenthub_session_user') || 
                     localStorage.getItem('studenthub_active_user') || 
                     localStorage.getItem('studenthub_saved_user');

  let storedProfile = null;
  try {
    const raw = localStorage.getItem('studenthub_user_profile');
    if (raw) storedProfile = JSON.parse(raw);
  } catch (err) {
    console.error('Error parsing stored profile:', err);
  }

  if (!storedProfile) {
    storedProfile = {
      fullname: 'Alex Johnson',
      email: 'alex.j@charusat.edu',
      rollNo: '2026-CS-104',
      phone: '+91 98765 43210',
      address: 'Room 204, CSPIT Hostel, CHARUSAT Campus, Changa',
      prefEmail: true,
      prefEvents: true
    };
  }

  if (activeUser) {
    if (activeUser.includes('@')) {
      storedProfile.email = activeUser;
      if (storedProfile.fullname === 'Alex Johnson') {
        const usernamePart = activeUser.split('@')[0];
        storedProfile.fullname = usernamePart.charAt(0).toUpperCase() + usernamePart.slice(1);
      }
    } else {
      storedProfile.rollNo = activeUser;
    }
  }

  if (nameInput || emailInput) {
    if (nameInput) nameInput.value = storedProfile.fullname || '';
    if (emailInput) emailInput.value = storedProfile.email || '';
    if (phoneInput) phoneInput.value = storedProfile.phone || '';
    if (addressInput) addressInput.value = storedProfile.address || '';
    if (emailNotifCheck) emailNotifCheck.checked = !!storedProfile.prefEmail;
    if (eventRemindCheck) eventRemindCheck.checked = !!storedProfile.prefEvents;

    if (displayName) displayName.textContent = storedProfile.fullname || 'Student User';
    if (displayRoll) displayRoll.textContent = storedProfile.rollNo || activeUser || '2026-CS-104';
  }

  if (profileForm) {
    profileForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const updatedProfile = {
        fullname: nameInput ? nameInput.value.trim() : storedProfile.fullname,
        email: emailInput ? emailInput.value.trim() : storedProfile.email,
        rollNo: displayRoll ? displayRoll.textContent.trim() : storedProfile.rollNo,
        phone: phoneInput ? phoneInput.value.trim() : storedProfile.phone,
        address: addressInput ? addressInput.value.trim() : storedProfile.address,
        prefEmail: emailNotifCheck ? emailNotifCheck.checked : true,
        prefEvents: eventRemindCheck ? eventRemindCheck.checked : true
      };

      localStorage.setItem('studenthub_user_profile', JSON.stringify(updatedProfile));
      localStorage.setItem('studenthub_active_user', updatedProfile.email || updatedProfile.rollNo);

      if (displayName) displayName.textContent = updatedProfile.fullname;
      if (displayRoll) displayRoll.textContent = updatedProfile.rollNo;

      if (statusMsg) {
        statusMsg.style.display = 'block';
        statusMsg.innerHTML = '✔ <strong>Profile Saved!</strong> Your details and credentials have been updated successfully.';
        statusMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      if (window.openStudentHubModal) {
        window.openStudentHubModal(
          'Profile Updated Successfully',
          `<p>Your student profile details have been saved to local storage.</p>
           <p><strong>Name:</strong> ${escapeHTML(updatedProfile.fullname)}<br>
              <strong>Roll No / ID:</strong> ${escapeHTML(updatedProfile.rollNo)}<br>
              <strong>Email:</strong> ${escapeHTML(updatedProfile.email)}<br>
              <strong>Phone:</strong> ${escapeHTML(updatedProfile.phone)}</p>`
        );
      }
    });
  }
}
