// CSP対応：確認ダイアログ・戻る/再読込・ログへスクロール・:has()フォールバック
document.addEventListener('DOMContentLoaded', () => {
  // 確認ダイアログ
  document.querySelectorAll('form.js-confirm').forEach(form => {
    form.addEventListener('submit', (e) => {
      const msg = form.dataset.confirm;
      if (msg && !window.confirm(msg)) e.preventDefault();
    });
  });

  // 戻る
  document.querySelectorAll('.js-back').forEach(btn => {
    btn.addEventListener('click', () => {
      if (history.length > 1) history.back();
      else location.href = location.pathname;
    });
  });

  // 再読込
  document.querySelectorAll('.js-reload').forEach(btn => {
    btn.addEventListener('click', () => location.reload());
  });

  // 「ログへ」スクロール
  document.querySelectorAll('.js-scroll-to-logs').forEach(btn => {
    btn.addEventListener('click', () => {
      const el = document.getElementById('logs');
      if (!el) return;
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  // :has() 非対応フォールバック（ID欄の表示切替）
  document.querySelectorAll("form[data-toggle='id-target']").forEach(form => {
    const row = form.querySelector('.id-input-row');
    const update = () => {
      const checked = form.querySelector("input[name='target']:checked");
      if (!row || !checked) return;
      row.style.display = (checked.value === 'id') ? 'flex' : 'none';
    };
    form.querySelectorAll("input[name='target']").forEach(r => r.addEventListener('change', update));
    update();
  });
});
