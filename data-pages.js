document.addEventListener('DOMContentLoaded', () => {
  initEventsDirectory();
  initStudentDirectory();
  initFAQDirectory();
});

async function fetchRecords(path, status, renderRecords) {
  status.textContent = 'Loading data…';

  try {
    const response = await fetch(path);
    if (!response.ok) {
      throw new Error(`Request failed with status ${response.status}.`);
    }

    const records = await response.json();
    if (!Array.isArray(records)) {
      throw new Error('The JSON response must be an array of records.');
    }

    renderRecords(records);
  } catch (error) {
    console.error(`Unable to load ${path}:`, error);
    status.replaceChildren();
    status.setAttribute('role', 'alert');
    status.append(document.createTextNode(`Could not load data (${error.message}). Run the project with a local web server, then try again. `));
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'btn btn-secondary';
    retry.textContent = 'Retry';
    retry.addEventListener('click', () => fetchRecords(path, status, renderRecords), { once: true });
    status.appendChild(retry);
  }
}

function paginateRecords(records, page, pageSize) {
  const pageCount = Math.max(1, Math.ceil(records.length / pageSize));
  const currentPage = Math.min(Math.max(page, 1), pageCount);
  const start = (currentPage - 1) * pageSize;
  return {
    currentPage,
    pageCount,
    start,
    visibleRecords: records.slice(start, start + pageSize)
  };
}

function renderPagination(container, page, pageCount, onPageChange) {
  container.replaceChildren();
  if (pageCount <= 1) return;

  const addButton = (label, targetPage, disabled = false, current = false) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-secondary';
    button.textContent = label;
    button.disabled = disabled;
    if (current) button.setAttribute('aria-current', 'page');
    button.addEventListener('click', () => onPageChange(targetPage));
    container.appendChild(button);
  };

  addButton('Previous', page - 1, page === 1);
  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
    addButton(String(pageNumber), pageNumber, false, pageNumber === page);
  }
  addButton('Next', page + 1, page === pageCount);
}

function updateRecordStatus(status, records, start, count, label) {
  status.setAttribute('role', 'status');
  if (records.length === 0) {
    status.textContent = `No ${label} match your search and filters.`;
    return;
  }
  status.textContent = `Showing ${start + 1}–${start + count} of ${records.length} ${label}.`;
}

function compareText(left, right) {
  return left.localeCompare(right, undefined, { sensitivity: 'base' });
}

function formatEventCategory(category) {
  const labels = {
    tech: 'Technical & Coding',
    workshop: 'Workshop',
    sports: 'Sports',
    career: 'Career',
    culture: 'Culture'
  };
  return labels[category] || category;
}

function initEventsDirectory() {
  const list = document.getElementById('events-list');
  if (!list) return;

  const status = document.getElementById('events-status');
  const pagination = document.getElementById('events-pagination');
  const search = document.getElementById('event-search');
  const category = document.getElementById('event-category');
  const sort = document.getElementById('event-sort');
  const pageSize = document.getElementById('event-page-size');
  let records = [];
  let page = 1;

  const render = () => {
    const query = search.value.trim().toLowerCase();
    const filtered = records.filter(event => {
      const matchesQuery = `${event.title} ${event.category} ${event.venue} ${event.description}`.toLowerCase().includes(query);
      return matchesQuery && (!category.value || event.category === category.value);
    });
    const [sortField, direction] = sort.value.split('-');
    filtered.sort((a, b) => {
      const result = sortField === 'date'
        ? a.date.localeCompare(b.date)
        : compareText(a.title, b.title);
      return direction === 'desc' ? -result : result;
    });

    const result = paginateRecords(filtered, page, Number(pageSize.value));
    page = result.currentPage;
    list.replaceChildren();
    result.visibleRecords.forEach(event => {
      const article = document.createElement('article');
      article.className = 'card flex flex-col justify-between h-full mb-0';
      const title = document.createElement('h2');
      title.textContent = event.title;
      const categoryText = document.createElement('p');
      categoryText.textContent = `Category: ${formatEventCategory(event.category)}`;
      const date = document.createElement('p');
      date.textContent = `Date: ${new Date(`${event.date}T12:00:00`).toLocaleDateString()}`;
      const venue = document.createElement('p');
      venue.textContent = `Venue: ${event.venue}`;
      const description = document.createElement('p');
      description.textContent = event.description;
      article.append(title, categoryText, date, venue, description);
      list.appendChild(article);
    });

    updateRecordStatus(status, filtered, result.start, result.visibleRecords.length, 'events');
    renderPagination(pagination, page, result.pageCount, nextPage => {
      page = nextPage;
      render();
    });
  };

  [search, category, sort, pageSize].forEach(control => {
    control.addEventListener(control === search ? 'input' : 'change', () => {
      page = 1;
      render();
    });
  });

  fetchRecords('data/events.json', status, data => {
    records = data;
    render();
  });
}

function initStudentDirectory() {
  const tableBody = document.getElementById('students-list');
  if (!tableBody) return;

  const status = document.getElementById('students-status');
  const pagination = document.getElementById('students-pagination');
  const search = document.getElementById('admin-search');
  const department = document.getElementById('student-department');
  const accountStatus = document.getElementById('student-status-filter');
  const sort = document.getElementById('student-sort');
  const pageSize = document.getElementById('student-page-size');
  let records = [];
  let page = 1;

  const render = () => {
    const query = search.value.trim().toLowerCase();
    const filtered = records.filter(student => {
      const matchesQuery = `${student.roll} ${student.name} ${student.email}`.toLowerCase().includes(query);
      return matchesQuery
        && (!department.value || student.department === department.value)
        && (!accountStatus.value || student.status === accountStatus.value);
    });
    const [sortField, direction] = sort.value.split('-');
    filtered.sort((a, b) => {
      const field = sortField === 'name' ? 'name' : sortField === 'roll' ? 'roll' : 'department';
      const result = compareText(a[field], b[field]);
      return direction === 'desc' ? -result : result;
    });

    const result = paginateRecords(filtered, page, Number(pageSize.value));
    page = result.currentPage;
    tableBody.replaceChildren();
    result.visibleRecords.forEach(student => {
      const row = document.createElement('tr');
      [student.roll, student.name, student.department, student.email, student.status].forEach(value => {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.appendChild(cell);
      });
      tableBody.appendChild(row);
    });

    updateRecordStatus(status, filtered, result.start, result.visibleRecords.length, 'students');
    renderPagination(pagination, page, result.pageCount, nextPage => {
      page = nextPage;
      render();
    });
  };

  [search, department, accountStatus, sort, pageSize].forEach(control => {
    control.addEventListener(control === search ? 'input' : 'change', () => {
      page = 1;
      render();
    });
  });

  fetchRecords('data/students.json', status, data => {
    records = data;
    render();
  });
}

function initFAQDirectory() {
  const list = document.getElementById('faq-list');
  if (!list) return;

  const status = document.getElementById('faq-status');
  const pagination = document.getElementById('faq-pagination');
  const search = document.getElementById('faq-search-input');
  const category = document.getElementById('faq-category');
  const sort = document.getElementById('faq-sort');
  const pageSize = document.getElementById('faq-page-size');
  const expandAll = document.getElementById('faq-expand-all');
  const collapseAll = document.getElementById('faq-collapse-all');
  let records = [];
  let page = 1;

  const render = () => {
    const query = search.value.trim().toLowerCase();
    const filtered = records.filter(faq => {
      const matchesQuery = `${faq.question} ${faq.answer} ${faq.category}`.toLowerCase().includes(query);
      return matchesQuery && (!category.value || faq.category === category.value);
    });
    const [sortField, direction] = sort.value.split('-');
    if (sortField === 'question') {
      filtered.sort((a, b) => {
        const result = compareText(a.question, b.question);
        return direction === 'desc' ? -result : result;
      });
    } else {
      filtered.sort((a, b) => a.id - b.id);
    }

    const result = paginateRecords(filtered, page, Number(pageSize.value));
    page = result.currentPage;
    list.replaceChildren();
    result.visibleRecords.forEach(faq => {
      const item = document.createElement('section');
      item.className = 'faq-item';
      const question = document.createElement('button');
      question.type = 'button';
      question.className = 'faq-question';
      question.setAttribute('aria-expanded', 'false');
      const questionText = document.createElement('span');
      questionText.textContent = faq.question;
      const icon = document.createElement('span');
      icon.className = 'faq-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '▼';
      question.append(questionText, icon);
      const answer = document.createElement('div');
      answer.className = 'faq-answer';
      const answerText = document.createElement('p');
      answerText.textContent = faq.answer;
      answer.appendChild(answerText);
      question.addEventListener('click', () => {
        const expanded = question.getAttribute('aria-expanded') === 'true';
        question.setAttribute('aria-expanded', String(!expanded));
        item.classList.toggle('active', !expanded);
      });
      item.append(question, answer);
      list.appendChild(item);
    });

    const noResults = document.getElementById('faq-no-results');
    if (noResults) noResults.style.display = filtered.length === 0 ? 'block' : 'none';
    updateRecordStatus(status, filtered, result.start, result.visibleRecords.length, 'FAQs');
    renderPagination(pagination, page, result.pageCount, nextPage => {
      page = nextPage;
      render();
    });
  };

  [search, category, sort, pageSize].forEach(control => {
    control.addEventListener(control === search ? 'input' : 'change', () => {
      page = 1;
      render();
    });
  });
  expandAll.addEventListener('click', () => {
    list.querySelectorAll('.faq-item').forEach(item => {
      item.classList.add('active');
      item.querySelector('.faq-question').setAttribute('aria-expanded', 'true');
    });
  });
  collapseAll.addEventListener('click', () => {
    list.querySelectorAll('.faq-item').forEach(item => {
      item.classList.remove('active');
      item.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    });
  });

  fetchRecords('data/faqs.json', status, data => {
    records = data;
    render();
  });
}
