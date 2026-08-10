document.addEventListener('DOMContentLoaded', () => {
  initThemeSwitcher();
  initHamburgerMenu();
  initNotificationBanner();
  initModalPopup();
  initContentSlider();
  initCollapsibleFAQ();
  initLoginCredentials();
  initProfileManagement();
  initRegisterValidation();
});

function initThemeSwitcher() {
  const themeToggleButtons = document.querySelectorAll('.theme-toggle-btn');
  const storedTheme = localStorage.getItem('studenthub_theme');
 
  if (storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.body.classList.add('dark-theme');
    updateToggleButtons(true);
  } else {
    document.body.classList.remove('dark-theme');
    updateToggleButtons(false);
  }

  themeToggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const isDark = document.body.classList.toggle('dark-theme');
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

function initLoginCredentials() {
  const loginForm = document.getElementById('login-form') || document.querySelector('form[action="dashboard.html"]');
  const loginIdInput = document.getElementById('login-id');
  const loginPassInput = document.getElementById('login-password');
  const rememberCheckbox = document.getElementById('remember');
  const statusMsg = document.getElementById('login-status-msg');

  if (loginIdInput && loginPassInput) {
    const savedUser = localStorage.getItem('studenthub_saved_user');
    const savedPass = localStorage.getItem('studenthub_saved_pass');
    const isRemembered = localStorage.getItem('studenthub_remember') === 'true';

    if (savedUser && isRemembered) {
      loginIdInput.value = savedUser;
      if (savedPass) loginPassInput.value = savedPass;
      if (rememberCheckbox) rememberCheckbox.checked = true;
      
      if (statusMsg) {
        statusMsg.style.display = 'block';
        statusMsg.innerHTML = '✔️ <em>Saved credentials restored for <strong>' + escapeHTML(savedUser) + '</strong></em> <button type="button" id="clear-saved-creds" style="background:none; border:none; color:var(--primary-color); text-decoration:underline; cursor:pointer; font-size:0.85rem; margin-left:8px;">Clear Saved Credentials</button>';
        
        setTimeout(() => {
          const clearBtn = document.getElementById('clear-saved-creds');
          if (clearBtn) {
            clearBtn.addEventListener('click', () => {
              localStorage.removeItem('studenthub_saved_user');
              localStorage.removeItem('studenthub_saved_pass');
              localStorage.removeItem('studenthub_remember');
              loginIdInput.value = '';
              loginPassInput.value = '';
              if (rememberCheckbox) rememberCheckbox.checked = false;
              statusMsg.style.display = 'none';
            });
          }
        }, 0);
      }
    }

    if (loginForm) {
      loginForm.addEventListener('submit', () => {
        const username = loginIdInput.value.trim();
        const password = loginPassInput.value;
        const shouldRemember = rememberCheckbox ? rememberCheckbox.checked : false;

        if (shouldRemember && username) {
          localStorage.setItem('studenthub_saved_user', username);
          localStorage.setItem('studenthub_saved_pass', password);
          localStorage.setItem('studenthub_remember', 'true');
        } else {
          localStorage.removeItem('studenthub_saved_user');
          localStorage.removeItem('studenthub_saved_pass');
          localStorage.setItem('studenthub_remember', 'false');
        }

        if (username) {
          sessionStorage.setItem('studenthub_session_user', username);
          localStorage.setItem('studenthub_active_user', username);
        }
      });
    }
  }

  updateUserHeaderSession();
}

function updateUserHeaderSession() {
  const activeUser = sessionStorage.getItem('studenthub_session_user') || localStorage.getItem('studenthub_active_user');
  const navLoginBtns = document.querySelectorAll('a.nav-btn');

  if (activeUser && navLoginBtns.length > 0) {
    navLoginBtns.forEach(btn => {
      if (btn.getAttribute('href') === 'login.html') {
        const displayUser = activeUser.includes('@') ? activeUser.split('@')[0] : activeUser;
        btn.textContent = `Logout (${displayUser})`;
        btn.setAttribute('title', `Logged in as ${activeUser}. Click to Logout.`);
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          sessionStorage.removeItem('studenthub_session_user');
          localStorage.removeItem('studenthub_active_user');
          window.location.href = 'login.html';
        });
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
    terms: form.querySelector('#terms')
  };

  const rules = {
    fullname: {
      test: value => /^[A-Za-z ]{2,50}$/.test(value.trim()),
      message: 'Enter a valid full name using letters and spaces only.'
    },
    studentid: {
      test: value => /^[A-Za-z0-9- ]{3,30}$/.test(value.trim()),
      message: 'Enter a valid roll or ID number.'
    },
    email: {
      test: value => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/.test(value.trim()),
      message: 'Enter a valid email address.'
    },
    password: {
      test: value => /^(?=.*[A-Za-z])(?=.*\d).{6,}$/.test(value),
      message: 'Password must be at least 6 characters and contain letters and numbers.'
    },
    confirmPassword: {
      test: value => value === fields.password.value,
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
      test: value => /^(?:\+91[\s-]?)?[6-9]\d{9}$/.test(value.trim()),
      message: 'Enter a valid 10-digit mobile number.'
    },
    terms: {
      test: () => fields.terms.checked,
      message: 'You must accept the terms of use.'
    }
  };

  function getErrorElement(element) {
    if (!element) return null;
    const group = element.closest('.form-group') || element.closest('.checkbox-group') || element.parentElement;
    let error = group && group.querySelector('.input-error');
    if (!error && group) {
      error = document.createElement('div');
      error.className = 'input-error';
      group.appendChild(error);
    }
    return error;
  }

  function setError(element, message) {
    if (!element) return;
    const error = getErrorElement(element);
    if (error) error.textContent = message;
    element.classList.add('input-invalid');
    if (element.closest('.form-group')) {
      element.closest('.form-group').classList.add('has-error');
    }
  }

  function clearError(element) {
    if (!element) return;
    const error = getErrorElement(element);
    if (error) error.textContent = '';
    element.classList.remove('input-invalid');
    if (element.closest('.form-group')) {
      element.closest('.form-group').classList.remove('has-error');
    }
  }

  function validateField(fieldName) {
    const field = fields[fieldName];
    const rule = rules[fieldName];
    if (!field || !rule) return true;
    const value = field.type === 'checkbox' ? field.checked : field.value || '';
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
    if (messages.length === 0) {
      summary.textContent = '';
      summary.style.display = 'none';
      return;
    }
    summary.innerHTML = `<strong>Please fix the following fields:</strong><ul>${messages.map(msg => `<li>${msg}</li>`).join('')}</ul>`;
    summary.style.display = 'block';
  }

  function validateForm() {
    let valid = true;
    const messages = [];
    Object.keys(rules).forEach(name => {
      if (!validateField(name)) {
        valid = false;
        messages.push(rules[name].message);
      }
    });
    displaySummaryMessage(messages);
    return valid;
  }

  function resolveFieldName(target) {
    if (!target || !target.id) return null;
    if (target.id === 'confirm-password') return 'confirmPassword';
    return target.id;
  }

  form.addEventListener('submit', (event) => {
    if (!validateForm()) {
      event.preventDefault();
      const firstInvalid = form.querySelector('.input-invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.focus();
      }
    }
  });

  form.addEventListener('input', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      clearError(target);
      validateField(fieldName);
    }
  });

  form.addEventListener('change', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      clearError(target);
      validateField(fieldName);
    }
  });

  form.addEventListener('focusout', (event) => {
    const target = event.target;
    const fieldName = resolveFieldName(target);
    if (fieldName && fields[fieldName]) {
      validateField(fieldName);
      displaySummaryMessage(
        Object.keys(rules)
          .filter(name => !rules[name].test(fields[name].type === 'checkbox' ? fields[name].checked : (fields[name].value || '')))
          .map(name => rules[name].message)
      );
    }
  });
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

      updateUserHeaderSession();

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


