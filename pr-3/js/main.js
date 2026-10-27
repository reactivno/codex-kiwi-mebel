const brief = document.getElementById('brief-dialog');
const form = document.getElementById('lead-form');
const status = document.getElementById('lead-status');
const submit = document.getElementById('finish');
const isStand = location.hostname.endsWith('github.io');
let selected = '', opener = null, dialogOpener = null, submitting = false, sent = false;
const progress = [...document.querySelectorAll('.progress span')];

function setStatus(text, state = '') {
  status.textContent = text;
  status.dataset.state = state;
}
function resetBrief() {
  form.reset();
  sent = false;
  document.getElementById('dimensions').value = '';
  document.getElementById('wishes').value = '';
  document.getElementById('request-preview').textContent = '';
  submit.textContent = 'Отправить заявку ↗';
  submit.disabled = isStand;
  document.getElementById('back2').hidden = false;
  setStatus(isStand ? 'Тестовый стенд: отправка заявок отключена. На основном сайте форма отправляет заявку на почту.' : '');
}
function showStep(number) {
  for (let i = 1; i <= 3; i++) document.getElementById('brief-step' + i).hidden = i !== number;
  progress.forEach((step, i) => step.classList.toggle('active', i === number - 1));
  document.getElementById('type-error').textContent = '';
  if (number === 2) document.getElementById('chosen-type').textContent = selected;
  brief.scrollTop = 0;
  const target = number === 1 ? brief.querySelector('[data-choice]') : number === 2 ? document.getElementById('dimensions') : document.getElementById('lead-phone');
  target.focus();
}
function choose(type) {
  selected = type;
  document.querySelectorAll('[data-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.choice === type)));
}
function openBrief(type, trigger) {
  opener = trigger || document.activeElement;
  resetBrief();
  choose(type || '');
  brief.showModal();
  showStep(1);
}
document.querySelectorAll('[data-brief]').forEach(button => button.addEventListener('click', () => openBrief(button.dataset.type, button)));
document.querySelectorAll('[data-choice]').forEach(button => button.addEventListener('click', () => {
  choose(button.dataset.choice);
  document.getElementById('type-error').textContent = '';
}));
document.getElementById('to-step2').onclick = () => {
  if (!selected) {
    document.getElementById('type-error').textContent = 'Выберите тип мебели, чтобы продолжить.';
    return;
  }
  showStep(2);
};
document.getElementById('back1').onclick = () => showStep(1);
document.getElementById('back2').onclick = () => { if (!submitting && !sent) showStep(2); };
document.getElementById('to-step3').onclick = () => {
  const dimensions = document.getElementById('dimensions').value.trim();
  const wishes = document.getElementById('wishes').value.trim();
  const message = 'Тип мебели: ' + selected + '\n' +
    (dimensions ? 'Примерные размеры: ' + dimensions + '\n' : 'Размеры пока не определены.\n') +
    (wishes ? 'Пожелания: ' + wishes + '\n' : '') +
    '\nХочу обсудить комплектацию, стоимость, сроки изготовления, доставку, сборку, гарантию и условия замера.';
  document.getElementById('request-preview').textContent = message;
  document.getElementById('lead-message').value = message;
  showStep(3);
};
form.addEventListener('submit', async event => {
  event.preventDefault();
  if (isStand || submitting || sent || !form.reportValidity()) return;
  if (!selected) { showStep(1); return; }
  const payload = new FormData(form);
  submitting = true;
  submit.disabled = true;
  submit.textContent = 'Отправляем…';
  form.setAttribute('aria-busy', 'true');
  const controls = [document.getElementById('back2'), brief.querySelector('.dialog-close')];
  controls.forEach(button => button.disabled = true);
  setStatus('Отправляем заявку…');
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);
  try {
    const response = await fetch(form.action, {
      method: 'POST', body: payload, headers: { Accept: 'application/json' }, signal: controller.signal
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || data?.ok !== true) {
      const errors = Array.isArray(data?.errors) ? data.errors.filter(item => typeof item === 'string') : [];
      throw new Error(errors.join('\n') || (typeof data?.error === 'string' ? data.error : 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните нам.'));
    }
    sent = true;
    form.reset();
    document.getElementById('back2').hidden = true;
    setStatus('Заявка отправлена! Мы свяжемся с вами по указанному телефону.', 'success');
  } catch (error) {
    const message = error.name === 'AbortError' ? 'Не удалось получить подтверждение отправки. Попробуйте позже или свяжитесь с нами по телефону.' : error instanceof TypeError ? 'Нет связи с сервером. Проверьте подключение и попробуйте ещё раз.' : error.message;
    setStatus(message, 'error');
  } finally {
    clearTimeout(timeout);
    submitting = false;
    form.removeAttribute('aria-busy');
    controls.forEach(button => button.disabled = false);
    submit.disabled = sent || isStand;
    submit.textContent = sent ? 'Заявка отправлена ✓' : 'Отправить заявку ↗';
  }
});
brief.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
brief.addEventListener('close', () => {
  selected = '';
  resetBrief();
  if (opener?.isConnected) opener.focus();
});
const menu=document.querySelector('.menu'),nav=document.getElementById('nav');menu.onclick=()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',String(open));};nav.querySelectorAll('a').forEach(a=>a.onclick=()=>{nav.classList.remove('open');menu.setAttribute('aria-expanded','false');});
const filters=[...document.querySelectorAll('[data-filter]')],projects=[...document.querySelectorAll('.project')];filters.forEach(b=>b.onclick=()=>{filters.forEach(x=>x.setAttribute('aria-pressed',String(x===b)));let count=0;projects.forEach(p=>{p.hidden=b.dataset.filter!=='all'&&p.dataset.category!==b.dataset.filter;if(!p.hidden)count++;});document.getElementById('filter-status').textContent='Показано примеров: '+count;});
const photo=document.getElementById('photo-dialog');document.querySelectorAll('[data-photo]').forEach(b=>b.onclick=()=>{dialogOpener=b;document.getElementById('photo-title').textContent=b.dataset.title;const img=document.getElementById('photo-full');img.src=b.dataset.photo;img.alt=b.dataset.title;photo.showModal();});photo.addEventListener('close',()=>dialogOpener?.focus());
const contact=document.getElementById('contact-dialog');document.querySelectorAll('[data-contact]').forEach(b=>b.onclick=()=>{dialogOpener=b;contact.showModal();});contact.addEventListener('close',()=>dialogOpener?.focus());document.getElementById('contact-brief').onclick=()=>{const trigger=dialogOpener;contact.close();openBrief('',trigger);};
document.querySelectorAll('dialog').forEach(d=>{d.querySelector('.dialog-close').onclick=()=>{if(d!==brief||!submitting)d.close();};d.addEventListener('click',e=>{if(e.target===d&&(d!==brief||!submitting)){const r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)d.close();}});});
