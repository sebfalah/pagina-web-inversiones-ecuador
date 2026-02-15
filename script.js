const form = document.getElementById("leadForm");
const formMessage = document.getElementById("formMessage");

form.addEventListener("submit", (event) => {
  event.preventDefault();

  const data = new FormData(form);
  const nombre = (data.get("nombre") || "").toString().trim();
  const correo = (data.get("correo") || "").toString().trim();
  const capital = Number(data.get("capital"));

  if (!nombre || !correo || !capital || capital < 5000) {
    formMessage.textContent = "Revisa los datos. El capital minimo sugerido es USD 5,000.";
    return;
  }

  formMessage.textContent = "Gracias. Recibimos tus datos y te contactaremos pronto.";
  form.reset();
});
