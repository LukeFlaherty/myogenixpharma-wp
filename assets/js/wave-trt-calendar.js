(function () {
  'use strict';
  const root = document.getElementById('wave-calendar');
  if (!root) return;
  const data = JSON.parse(document.getElementById('wave-cal-data').textContent);
  const search = root.querySelector('#wave-cal-search');
  const patient = root.querySelector('#wave-cal-patient');
  const toggles = Array.from(root.querySelectorAll('.wave-cal-legend input'));
  const range = root.querySelector('#wave-cal-range');
  const dialog = root.querySelector('#wave-day-dialog');
  let filtered = [], byDay = {}, agendaLimit = 40;
  function element(tag, text, className) {
    const item = document.createElement(tag);
    if (text !== undefined) item.textContent = text;
    if (className) item.className = className;
    return item;
  }
  function pretty(date) {
    const bits = date.split('-').map(Number);
    return new Date(bits[0], bits[1] - 1, bits[2]).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'});
  }
  function overdue(event) { return event.state === 'scheduled' && event.date < data.today; }
  function eventCard(event) {
    const card = element('article', undefined, 'wave-calendar-event');
    const top = element('div', undefined, 'wave-event-top');
    top.append(element('span', data.types[event.type], 'wave-event-tag ' + event.type));
    top.append(element('time', pretty(event.date)));
    if (overdue(event)) top.append(element('span', 'Past due · verify', 'wave-overdue'));
    if (event.state === 'closed') top.append(element('span', 'Closed', 'wave-closed'));
    card.append(top, element('h3', event.patient + ' · ' + event.title), element('p', event.detail));
    const links = element('div', undefined, 'wave-event-links');
    const personLink = element('a', 'Patient & actions'); personLink.href = event.patientUrl;
    const sourceLink = element('a', 'Source record #' + event.recordId); sourceLink.href = event.source;
    links.append(personLink, sourceLink); card.append(links); return card;
  }
  function renderAgenda() {
    const list = root.querySelector('#wave-cal-agenda'); list.replaceChildren();
    const events = filtered.filter(event => range.value === 'all' || (range.value === 'overdue' ? overdue(event) : event.date >= data.today));
    if (!events.length) list.append(element('p', 'No matching events in this range. Choose “All events in this year” to review history.', 'wave-cal-empty'));
    events.slice(0, agendaLimit).forEach(event => list.append(eventCard(event)));
    root.querySelector('#wave-cal-more').hidden = events.length <= agendaLimit;
  }
  function update() {
    const query = search.value.trim().toLowerCase();
    const types = new Set(toggles.filter(input => input.checked).map(input => input.value));
    const matchesPerson = event => (!patient.value || event.patientId === patient.value) && (!query || (event.patient + ' ' + event.recordId).toLowerCase().includes(query));
    filtered = data.events.filter(event => types.has(event.type) && matchesPerson(event));
    byDay = {};
    filtered.forEach(event => { (byDay[event.date] ||= []).push(event); });
    root.querySelectorAll('.wave-day').forEach(button => {
      const events = byDay[button.dataset.date] || [];
      const marks = button.querySelector('.wave-day-marks'); marks.replaceChildren();
      Array.from(new Set(events.map(event => event.type))).slice(0, 4).forEach(type => marks.append(element('i', undefined, 'wave-cal-dot ' + type)));
      if (events.length) marks.append(element('b', String(events.length)));
      button.classList.toggle('has-events', events.length > 0);
      button.classList.toggle('has-overdue', events.some(overdue));
      const label = pretty(button.dataset.date) + ' · ' + events.length + (events.length === 1 ? ' event' : ' events');
      button.setAttribute('aria-label', label);
      button.title = label + (events.length ? '\n' + events.slice(0, 6).map(event => event.patient + ': ' + event.title).join('\n') + (events.length > 6 ? '\nMore events — click to view all' : '') : '');
    });
    root.querySelectorAll('[data-month-count]').forEach(label => {
      label.textContent = filtered.filter(event => event.date.startsWith(label.dataset.monthCount)).length + ' events';
    });
    const affected = new Set(filtered.map(event => event.patientId)).size;
    root.querySelector('#wave-cal-summary').textContent = filtered.length + ' visible events · ' + affected + ' patients with events · ' + filtered.filter(overdue).length + ' past-due scheduled items · Outlined date = today';
    const gaps = root.querySelector('#wave-cal-gaps'); gaps.replaceChildren();
    const missing = data.undated.filter(person => (!patient.value || person.patientId === patient.value) && (!query || person.name.toLowerCase().includes(query) || data.events.some(event => event.patientId === person.patientId && String(event.recordId).includes(query))));
    missing.forEach(person => {
      const row = element('p'); const link = element('a', person.name); link.href = person.url;
      row.append(link, element('span', person.reason)); gaps.append(row);
    });
    if (!missing.length) gaps.append(element('p', 'No scheduling gaps for the selected patients.', 'wave-cal-empty'));
    agendaLimit = 40; renderAgenda();
  }
  root.querySelectorAll('.wave-day').forEach(button => button.addEventListener('click', function () {
    root.querySelector('#wave-day-title').textContent = pretty(button.dataset.date);
    const body = root.querySelector('#wave-day-events'); body.replaceChildren();
    const events = byDay[button.dataset.date] || [];
    events.forEach(event => body.append(eventCard(event)));
    if (!events.length) body.append(element('p', 'No events match the current filters on this day. Schedule a check-in by setting a follow-up date on the patient dashboard.', 'wave-cal-empty'));
    dialog.showModal();
  }));
  root.querySelector('#wave-day-close').addEventListener('click', () => dialog.close());
  search.addEventListener('input', update); patient.addEventListener('change', update);
  toggles.forEach(input => input.addEventListener('change', update));
  range.addEventListener('change', () => { agendaLimit = 40; renderAgenda(); });
  root.querySelector('#wave-cal-more').addEventListener('click', () => { agendaLimit += 40; renderAgenda(); });
  root.querySelector('#wave-cal-reset').addEventListener('click', () => {
    search.value = ''; patient.value = ''; toggles.forEach(input => { input.checked = input.value !== 'suggested'; }); range.value = 'upcoming'; update();
  });
  update();
}());
