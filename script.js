document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('student-form');
  if (!form) return;

  const status = document.getElementById('student-status');
  const tableBody = document.getElementById('student-table-body');
  const idInput = document.getElementById('student-id');
  const nameInput = document.getElementById('student-name');
  const emailInput = document.getElementById('student-email');
  const courseInput = document.getElementById('student-course');
  const formTitle = document.getElementById('student-form-title');
  const submitButton = document.getElementById('student-submit');
  const cancelButton = document.getElementById('cancel-edit');
  const apiUrl = 'api.php';
  let csrfToken = '';

  function setStatus(message, isError = false) {
    status.textContent = message;
    status.setAttribute('role', isError ? 'alert' : 'status');
    status.classList.toggle('api-status-error', isError);
    status.classList.toggle('api-status-success', !isError);
  }

  async function requestApi(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.method && options.method !== 'GET' ? { 'X-CSRF-Token': csrfToken } : {}),
        ...options.headers
      }
    });

    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error(`The server returned an invalid JSON response (HTTP ${response.status}).`);
    }

    if (!response.ok || result.success !== true) {
      throw new Error(result.message || `The request failed (HTTP ${response.status}).`);
    }
    return result;
  }

  function makeCell(value) {
    const cell = document.createElement('td');
    cell.textContent = value;
    return cell;
  }

  function renderStudents(students) {
    tableBody.replaceChildren();

    if (students.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 4;
      cell.textContent = 'No student records are available.';
      row.appendChild(cell);
      tableBody.appendChild(row);
      return;
    }

    students.forEach(student => {
      const row = document.createElement('tr');
      row.append(
        makeCell(student.full_name),
        makeCell(student.email),
        makeCell(student.course)
      );

      const actions = document.createElement('td');
      actions.className = 'practical-api-actions';

      const editButton = document.createElement('button');
      editButton.type = 'button';
      editButton.className = 'btn btn-secondary';
      editButton.textContent = 'Edit';
      editButton.setAttribute('aria-label', `Edit ${student.full_name}`);
      editButton.addEventListener('click', () => beginEdit(student));

      const deleteButton = document.createElement('button');
      deleteButton.type = 'button';
      deleteButton.className = 'btn btn-danger';
      deleteButton.textContent = 'Delete';
      deleteButton.setAttribute('aria-label', `Delete ${student.full_name}`);
      deleteButton.addEventListener('click', () => deleteStudent(student));

      actions.append(editButton, deleteButton);
      row.appendChild(actions);
      tableBody.appendChild(row);
    });
  }

  async function loadStudents() {
    setStatus('Loading student records…');
    try {
      const result = await requestApi(apiUrl);
      if (!Array.isArray(result.data) || typeof result.csrf_token !== 'string') {
        throw new Error('The API returned an unexpected student list response.');
      }
      csrfToken = result.csrf_token;
      renderStudents(result.data);
      setStatus(`Loaded ${result.data.length} student record${result.data.length === 1 ? '' : 's'}.`);
    } catch (error) {
      tableBody.replaceChildren();
      setStatus(`${error.message} Check that PHP and MySQL are running, then retry.`, true);
      const retryRow = document.createElement('tr');
      const retryCell = document.createElement('td');
      retryCell.colSpan = 4;
      const retryButton = document.createElement('button');
      retryButton.type = 'button';
      retryButton.className = 'btn btn-secondary';
      retryButton.textContent = 'Retry loading';
      retryButton.addEventListener('click', loadStudents);
      retryCell.appendChild(retryButton);
      retryRow.appendChild(retryCell);
      tableBody.appendChild(retryRow);
    }
  }

  function beginEdit(student) {
    idInput.value = String(student.id);
    nameInput.value = student.full_name;
    emailInput.value = student.email;
    courseInput.value = student.course;
    formTitle.textContent = `Edit student: ${student.full_name}`;
    submitButton.textContent = 'Save changes';
    cancelButton.hidden = false;
    nameInput.focus();
    setStatus(`Editing ${student.full_name}.`);
  }

  function resetForm() {
    form.reset();
    idInput.value = '';
    formTitle.textContent = 'Add a student';
    submitButton.textContent = 'Add student';
    cancelButton.hidden = true;
  }

  async function deleteStudent(student) {
    if (!window.confirm(`Delete ${student.full_name} from the student list?`)) return;

    setStatus(`Deleting ${student.full_name}…`);
    try {
      const result = await requestApi(`${apiUrl}?id=${encodeURIComponent(student.id)}`, {
        method: 'DELETE'
      });
      await loadStudents();
      setStatus(result.message);
    } catch (error) {
      setStatus(error.message, true);
    }
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const id = idInput.value;
    const payload = {
      full_name: nameInput.value.trim(),
      email: emailInput.value.trim(),
      course: courseInput.value.trim()
    };
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${apiUrl}?id=${encodeURIComponent(id)}` : apiUrl;

    submitButton.disabled = true;
    setStatus(id ? 'Saving student changes…' : 'Adding student…');
    try {
      const result = await requestApi(url, {
        method,
        body: JSON.stringify(payload)
      });
      resetForm();
      await loadStudents();
      setStatus(result.message);
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      submitButton.disabled = false;
    }
  });

  cancelButton.addEventListener('click', () => {
    resetForm();
    setStatus('Edit cancelled.');
  });

  loadStudents();
});
