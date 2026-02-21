// assets/js/script.js
(function () {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");
  const btnOpen = document.getElementById("btnSidebar");
  const btnClose = document.getElementById("btnCloseSidebar");

  if (!sidebar || !overlay || !btnOpen) return;

  function openSidebar() {
    sidebar.classList.add("open");
    overlay.classList.add("show");
    document.body.classList.add("no-scroll");
  }

  function closeSidebar() {
    sidebar.classList.remove("open");
    overlay.classList.remove("show");
    document.body.classList.remove("no-scroll");
  }

  btnOpen.addEventListener("click", openSidebar);
  overlay.addEventListener("click", closeSidebar);
  if (btnClose) btnClose.addEventListener("click", closeSidebar);

  // auto-close ketika klik link di mobile
  sidebar.addEventListener("click", function (e) {
    const a = e.target.closest("a");
    if (!a) return;
    if (window.innerWidth <= 768) closeSidebar();
  });

  // reset jika resize ke desktop
  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) closeSidebar();
  });
})();
