const leadForm = document.getElementById("leadForm");
const formMessage = document.getElementById("formMessage");

if (leadForm && formMessage) {
  leadForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const data = new FormData(leadForm);
    const nombre = (data.get("nombre") || "").toString().trim();
    const correo = (data.get("correo") || "").toString().trim();
    const capital = Number(data.get("capital"));

    if (!nombre || !correo || !capital || capital < 5000) {
      formMessage.textContent = "Revisa los datos. El capital mínimo sugerido es USD 5,000.";
      return;
    }

    formMessage.textContent = "Gracias. Recibimos tus datos y te contactaremos pronto.";
    leadForm.reset();
  });
}

const prospectForm = document.getElementById("prospectForm");
const prospectMessage = document.getElementById("prospectMessage");

if (prospectForm && prospectMessage) {
  prospectForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const data = new FormData(prospectForm);
    const edad = Number(data.get("edad"));
    const capital = Number(data.get("capital"));
    const objetivos = data.getAll("objetivos");

    if (!edad || edad < 18 || edad > 100) {
      prospectMessage.textContent = "Ingresa una edad válida entre 18 y 100 años.";
      return;
    }

    if (objetivos.length === 0) {
      prospectMessage.textContent = "Selecciona al menos un objetivo de inversión.";
      return;
    }

    if (!capital || capital < 5000) {
      prospectMessage.textContent = "El monto mínimo sugerido para iniciar es USD 5,000.";
      return;
    }

    prospectMessage.textContent = "Gracias. Recibimos tu perfil y te contactaremos con una propuesta inicial.";
    prospectForm.reset();
  });
}
