document.addEventListener("DOMContentLoaded", () => {
  initThemeToggle();
  initFadeIn();
  initLoginLoader();
  initPasswordToggles();
  initCountryCityPicker();
  initToasts();
  initRecruiterShell();
  initRecruiterModals();
  initSettingsTabs();
  initMissionBuilder();
  initDocumentsBuilder();
});

function initThemeToggle() {
  const toggle = document.querySelector("[data-theme-toggle]");
  if (!toggle) {
    return;
  }

  const label = toggle.querySelector("[data-theme-label]");
  const root = document.documentElement;

  const render = () => {
    const isDark = root.classList.contains("dark");
    toggle.setAttribute("aria-pressed", String(isDark));
    if (label) {
      label.textContent = isDark ? "Dark" : "Light";
    }
  };

  toggle.addEventListener("click", () => {
    const nextTheme = root.classList.contains("dark") ? "light" : "dark";
    root.classList.toggle("dark", nextTheme === "dark");
    localStorage.setItem("kibarejob-theme", nextTheme);
    render();
  });

  render();
}

function initFadeIn() {
  const elements = document.querySelectorAll(".fade-in");
  if (!elements.length) {
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  elements.forEach((element) => observer.observe(element));
}

function initLoginLoader() {
  const form = document.querySelector("[data-login-form]");
  if (!form) {
    return;
  }

  form.addEventListener("submit", () => {
    const label = form.querySelector("[data-login-label]");
    const loader = form.querySelector("[data-login-loader]");
    if (label && loader) {
      label.classList.add("hidden");
      loader.classList.remove("hidden");
    }
  });
}

function initToasts() {
  document.querySelectorAll("[data-toast]").forEach((toast) => {
    window.setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(1rem)";
      window.setTimeout(() => toast.remove(), 550);
    }, 4500);
  });
}

function initPasswordToggles() {
  document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
    const wrapper = toggle.closest(".relative");
    const input = wrapper ? wrapper.querySelector("[data-password-input]") : null;
    const icon = toggle.querySelector("[data-password-icon]");

    if (!input) {
      return;
    }

    toggle.addEventListener("click", () => {
      const shouldShow = input.type === "password";
      input.type = shouldShow ? "text" : "password";
      toggle.setAttribute("aria-label", shouldShow ? "Cacher le mot de passe" : "Afficher le mot de passe");
      if (icon) {
        icon.textContent = shouldShow ? "Cacher" : "Voir";
      }
    });
  });
}

function initCountryCityPicker() {
  const picker = document.querySelector("[data-country-picker]");
  const cityPicker = document.querySelector("[data-city-picker]");
  if (!picker || !cityPicker) {
    return;
  }

  const trigger = picker.querySelector("[data-country-trigger]");
  const panel = picker.querySelector("[data-country-panel]");
  const search = picker.querySelector("[data-country-search]");
  const list = picker.querySelector("[data-country-list]");
  const codeInput = picker.querySelector("[data-country-code]");
  const nameInput = picker.querySelector("[data-country-name]");
  const label = picker.querySelector("[data-country-label]");
  const cityTrigger = cityPicker.querySelector("[data-city-trigger]");
  const cityPanel = cityPicker.querySelector("[data-city-panel]");
  const citySearch = cityPicker.querySelector("[data-city-search]");
  const cityList = cityPicker.querySelector("[data-city-list]");
  const cityValue = cityPicker.querySelector("[data-city-value]");
  const cityLabel = cityPicker.querySelector("[data-city-label]");
  const defaultCode = picker.dataset.defaultCountry || "BF";
  const countries = buildCountries();
  let currentCities = [];

  const selectCountry = (country, keepSelectedCity = false) => {
    codeInput.value = country.code;
    nameInput.value = country.name;
    label.innerHTML = flagMarkup(country.code) + `<span>${escapeHtml(country.name)}</span>`;
    panel.classList.add("hidden");
    trigger.setAttribute("aria-expanded", "false");
    if (!keepSelectedCity) {
      cityPicker.dataset.selectedCity = "";
    }
    loadCities(country, cityPicker).then((cities) => {
      currentCities = cities;
      renderCities(currentCities);
    });
  };

  const selectCity = (city) => {
    cityValue.value = city;
    cityLabel.textContent = city;
    cityPicker.dataset.selectedCity = city;
    cityPanel.classList.add("hidden");
    cityTrigger.setAttribute("aria-expanded", "false");
  };

  const renderCities = (cities) => {
    const query = normalize(citySearch.value);
    cityList.innerHTML = "";

    cities
      .filter((city) => normalize(city).includes(query))
      .slice(0, 18)
      .forEach((city) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className =
          "flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100";
        button.innerHTML = `<span class="min-w-0 flex-1 truncate">${escapeHtml(city)}</span>`;
        button.addEventListener("click", () => selectCity(city));
        cityList.appendChild(button);
      });

    if (!cityList.children.length) {
      const empty = document.createElement("p");
      empty.className = "px-3 py-4 text-sm font-semibold text-slate-500";
      empty.textContent = "Aucune ville trouvee.";
      cityList.appendChild(empty);
    }
  };

  const renderCountries = () => {
    const query = normalize(search.value);
    list.innerHTML = "";

    countries
      .filter((country) => {
        return (
          normalize(country.name).includes(query) ||
          normalize(country.enName).includes(query) ||
          country.code.toLowerCase().includes(query)
        );
      })
      .slice(0, 14)
      .forEach((country) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className =
          "flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100";
        button.innerHTML =
          flagMarkup(country.code) +
          `<span class="min-w-0 flex-1 truncate">${escapeHtml(country.name)}</span>`;
        button.addEventListener("click", () => selectCountry(country));
        list.appendChild(button);
      });

    if (!list.children.length) {
      const empty = document.createElement("p");
      empty.className = "px-3 py-4 text-sm font-semibold text-slate-500";
      empty.textContent = "Aucun pays trouve.";
      list.appendChild(empty);
    }
  };

  trigger.addEventListener("click", () => {
    const isHidden = panel.classList.contains("hidden");
    panel.classList.toggle("hidden", !isHidden);
    trigger.setAttribute("aria-expanded", String(isHidden));
    if (isHidden) {
      search.focus();
      renderCountries();
    }
  });

  document.addEventListener("click", (event) => {
    if (!picker.contains(event.target)) {
      panel.classList.add("hidden");
      trigger.setAttribute("aria-expanded", "false");
    }
  });

  search.addEventListener("input", renderCountries);

  cityTrigger.addEventListener("click", () => {
    const isHidden = cityPanel.classList.contains("hidden");
    cityPanel.classList.toggle("hidden", !isHidden);
    cityTrigger.setAttribute("aria-expanded", String(isHidden));
    if (isHidden) {
      citySearch.focus();
      renderCities(currentCities);
    }
  });

  document.addEventListener("click", (event) => {
    if (!cityPicker.contains(event.target)) {
      cityPanel.classList.add("hidden");
      cityTrigger.setAttribute("aria-expanded", "false");
    }
  });

  citySearch.addEventListener("input", () => renderCities(currentCities));

  const initialCountry =
    countries.find((country) => country.code === defaultCode) ||
    countries.find((country) => country.code === "BF");
  selectCountry(initialCountry, true);
}

function initRecruiterShell() {
  const shell = document.querySelector("[data-recruiter-shell]");
  if (!shell) {
    return;
  }

  const sidebar = shell.querySelector("[data-recruiter-sidebar]");
  const overlay = shell.querySelector("[data-recruiter-overlay]");
  const toggle = shell.querySelector("[data-recruiter-sidebar-toggle]");
  const notificationToggle = shell.querySelector("[data-notification-toggle]");
  const notificationPanel = shell.querySelector("[data-notification-panel]");

  const setSidebarOpen = (isOpen) => {
    if (!sidebar || !overlay) {
      return;
    }
    sidebar.classList.toggle("-translate-x-full", !isOpen);
    overlay.classList.toggle("hidden", !isOpen);
  };

  if (toggle) {
    toggle.addEventListener("click", () => {
      setSidebarOpen(sidebar.classList.contains("-translate-x-full"));
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => setSidebarOpen(false));
  }

  if (notificationToggle && notificationPanel) {
    notificationToggle.addEventListener("click", (event) => {
      event.stopPropagation();
      notificationPanel.classList.toggle("hidden");
    });

    document.addEventListener("click", (event) => {
      if (!notificationPanel.contains(event.target) && !notificationToggle.contains(event.target)) {
        notificationPanel.classList.add("hidden");
      }
    });
  }
}

function initRecruiterModals() {
  document.querySelectorAll("[data-application-card]").forEach((card) => {
    const target = card.dataset.modalTarget;
    const modal = document.querySelector(`[data-modal="${target}"]`);
    if (!modal) {
      return;
    }

    const closeButtons = modal.querySelectorAll("[data-modal-close]");
    const close = () => {
      modal.classList.add("hidden");
      modal.classList.remove("flex");
      document.body.classList.remove("overflow-hidden");
    };

    card.addEventListener("click", () => {
      modal.classList.remove("hidden");
      modal.classList.add("flex");
      document.body.classList.add("overflow-hidden");
    });

    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        close();
      }
    });

    closeButtons.forEach((button) => button.addEventListener("click", close));
  });
}

function initSettingsTabs() {
  const wrapper = document.querySelector("[data-settings-tabs]");
  if (!wrapper) {
    return;
  }

  const tabs = wrapper.querySelectorAll("[data-settings-tab]");
  const panels = wrapper.querySelectorAll("[data-settings-panel]");

  const activate = (id) => {
    tabs.forEach((tab) => {
      const isActive = tab.dataset.settingsTab === id;
      tab.classList.toggle("bg-gradient-to-r", isActive);
      tab.classList.toggle("from-secondary", isActive);
      tab.classList.toggle("to-primary", isActive);
      tab.classList.toggle("text-white", isActive);
      tab.classList.toggle("text-slate-600", !isActive);
      tab.classList.toggle("hover:bg-slate-100", !isActive);
    });

    panels.forEach((panel) => {
      panel.classList.toggle("hidden", panel.dataset.settingsPanel !== id);
    });
  };

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => activate(tab.dataset.settingsTab));
  });

  if (window.location.hash === "#subscription") {
    activate("subscription");
  }
}

function initMissionBuilder() {
  const builder = document.querySelector("[data-missions-builder]");
  if (!builder) {
    return;
  }

  const list = builder.querySelector("[data-missions-list]");
  const addButton = builder.querySelector("[data-add-mission]");
  if (!list || !addButton) {
    return;
  }

  const inputClasses =
    "w-full rounded-2xl border border-slate-200 px-4 py-3 font-semibold outline-none focus:ring-4 focus:ring-secondary/20";

  const refreshPlaceholders = () => {
    list.querySelectorAll('input[name="missions[]"]').forEach((input, index) => {
      input.placeholder = `Mission ${index + 1}`;
    });
  };

  const createRemoveButton = () => {
    const button = document.createElement("button");
    button.type = "button";
    button.className =
      "grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-slate-100 font-black text-slate-500 transition hover:bg-slate-200 hover:text-primary";
    button.setAttribute("aria-label", "Supprimer cette mission");
    button.textContent = "x";
    button.addEventListener("click", () => {
      button.closest("[data-mission-row]")?.remove();
      refreshPlaceholders();
    });

    return button;
  };

  const addMission = () => {
    const row = document.createElement("div");
    row.className = "flex gap-2";
    row.dataset.missionRow = "";

    const input = document.createElement("input");
    input.name = "missions[]";
    input.className = inputClasses;
    input.autocomplete = "off";

    row.appendChild(input);
    row.appendChild(createRemoveButton());
    list.appendChild(row);
    refreshPlaceholders();
    input.focus();
  };

  addButton.addEventListener("click", addMission);
}

function initDocumentsBuilder() {
  const builder = document.querySelector("[data-documents-builder]");
  if (!builder) {
    return;
  }

  const list = builder.querySelector("[data-documents-list]");
  const select = builder.querySelector("[data-document-select]");
  const addButton = builder.querySelector("[data-add-document]");
  if (!list || !select || !addButton) {
    return;
  }

  const currentDocuments = () =>
    Array.from(list.querySelectorAll('input[name="requiredDocuments[]"], input[name="recommendedDocuments[]"]')).map((input) =>
      normalize(input.value)
    );

  const renderDocumentState = (row) => {
    const input = row.querySelector('input[name="requiredDocuments[]"], input[name="recommendedDocuments[]"]');
    const toggle = row.querySelector("[data-document-toggle]");
    if (!input || !toggle) {
      return;
    }

    const isRequired = input.name === "requiredDocuments[]";
    toggle.textContent = isRequired ? "Obligatoire" : "Optionnel";
    toggle.className = isRequired
      ? "rounded-full bg-primary/10 px-3 py-1 text-xs font-black text-primary transition hover:bg-primary/15"
      : "rounded-full bg-secondary/10 px-3 py-1 text-xs font-black text-primary transition hover:bg-secondary/15";
    row.classList.toggle("bg-slate-50", isRequired);
    row.classList.toggle("bg-white", !isRequired);
  };

  const toggleDocumentState = (row) => {
    const input = row.querySelector('input[name="requiredDocuments[]"], input[name="recommendedDocuments[]"]');
    if (!input) {
      return;
    }

    input.name = input.name === "requiredDocuments[]" ? "recommendedDocuments[]" : "requiredDocuments[]";
    renderDocumentState(row);
  };

  const bindDocumentRow = (row) => {
    const toggle = row.querySelector("[data-document-toggle]");
    if (toggle) {
      toggle.addEventListener("click", () => toggleDocumentState(row));
    }
    renderDocumentState(row);
  };

  const addDocument = (name) => {
    const label = String(name || "").trim();
    if (!label || currentDocuments().includes(normalize(label))) {
      return;
    }

    const row = document.createElement("div");
    row.className = "flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3";
    row.dataset.documentRow = "";

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "recommendedDocuments[]";
    input.value = label;

    const title = document.createElement("span");
    title.className = "font-black text-slate-700";
    title.textContent = label;

    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.dataset.documentToggle = "";

    const remove = document.createElement("button");
    remove.type = "button";
    remove.className =
      "grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 font-black text-slate-500 transition hover:bg-slate-200 hover:text-primary";
    remove.setAttribute("aria-label", "Supprimer ce document");
    remove.textContent = "x";
    remove.addEventListener("click", () => row.remove());

    row.appendChild(input);
    row.appendChild(title);
    row.appendChild(toggle);
    row.appendChild(remove);
    list.appendChild(row);
    bindDocumentRow(row);
  };

  list.querySelectorAll("[data-document-row]").forEach(bindDocumentRow);

  addButton.addEventListener("click", () => {
    addDocument(select.value);
  });
}

async function loadCities(country, cityPicker) {
  const selectedCity = cityPicker.dataset.selectedCity || "";
  const cityValue = cityPicker.querySelector("[data-city-value]");
  const cityLabel = cityPicker.querySelector("[data-city-label]");
  const capital = await resolveCapital(country);
  cityValue.value = "";
  cityLabel.textContent = "Chargement des villes...";

  let cities = CITY_FALLBACKS[country.code] || [];

  try {
    const response = await fetch("https://countriesnow.space/api/v0.1/countries/cities", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ country: country.enName }),
    });
    const payload = await response.json();
    if (!payload.error && Array.isArray(payload.data) && payload.data.length) {
      cities = payload.data;
    }
  } catch (error) {
    cities = CITY_FALLBACKS[country.code] || cities;
  }

  if (!cities.length) {
    cities = [capital || "Autre ville"];
  }

  if (capital && !cities.includes(capital)) {
    cities.unshift(capital);
  }

  const uniqueCities = [...new Set(cities)].sort((a, b) => a.localeCompare(b));
  const defaultCity = selectedCity || capital || uniqueCities[0];

  if (defaultCity && !uniqueCities.includes(defaultCity)) {
    uniqueCities.unshift(defaultCity);
  }

  cityValue.value = defaultCity;
  cityLabel.textContent = defaultCity;
  cityPicker.dataset.selectedCity = defaultCity;

  return uniqueCities;
}

async function resolveCapital(country) {
  if (CAPITAL_FALLBACKS[country.code]) {
    return CAPITAL_FALLBACKS[country.code];
  }

  try {
    const response = await fetch(`https://restcountries.com/v3.1/alpha/${country.code}?fields=capital`);
    const payload = await response.json();
    if (Array.isArray(payload.capital) && payload.capital[0]) {
      return payload.capital[0];
    }
  } catch (error) {
    return "";
  }

  return "";
}

function buildCountries() {
  const frenchNames = new Intl.DisplayNames(["fr"], { type: "region" });
  const englishNames = new Intl.DisplayNames(["en"], { type: "region" });

  return ISO_COUNTRY_CODES.map((code) => ({
    code,
    name: frenchNames.of(code) || code,
    enName: englishNames.of(code) || code,
    flag: countryFlag(code),
  })).sort((a, b) => a.name.localeCompare(b.name));
}

function countryFlag(code) {
  return code
    .toUpperCase()
    .replace(/./g, (char) => String.fromCodePoint(127397 + char.charCodeAt()));
}

function flagMarkup(code) {
  const lowerCode = code.toLowerCase();
  return `<img class="h-4 w-6 shrink-0 rounded-sm object-cover" src="https://flagcdn.com/24x18/${lowerCode}.png" alt="">`;
}

function escapeHtml(value) {
  const span = document.createElement("span");
  span.textContent = value;
  return span.innerHTML;
}

function normalize(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

const CITY_FALLBACKS = {
  BF: [
    "Ouagadougou",
    "Banfora",
    "Bobo-Dioulasso",
    "Dedougou",
    "Fada N'Gourma",
    "Gaoua",
    "Kaya",
    "Koudougou",
    "Ouahigouya",
    "Tenkodogo",
    "Ziniaré",
  ],
  CI: ["Abidjan", "Bouake", "Daloa", "Korhogo", "San-Pedro", "Yamoussoukro"],
  GH: ["Accra", "Cape Coast", "Kumasi", "Sekondi-Takoradi", "Tamale", "Tema"],
  ML: ["Bamako", "Kayes", "Mopti", "Segou", "Sikasso", "Timbuktu"],
  NE: ["Agadez", "Diffa", "Maradi", "Niamey", "Tahoua", "Zinder"],
  SN: ["Dakar", "Diourbel", "Kaolack", "Saint-Louis", "Thies", "Ziguinchor"],
  TG: ["Atakpame", "Kara", "Lome", "Sokode", "Tsevie"],
};

const CAPITAL_FALLBACKS = {
  BF: "Ouagadougou",
  BJ: "Porto-Novo",
  CI: "Yamoussoukro",
  CM: "Yaounde",
  FR: "Paris",
  GH: "Accra",
  ML: "Bamako",
  NE: "Niamey",
  NG: "Abuja",
  SN: "Dakar",
  TG: "Lome",
  US: "Washington",
};

const ISO_COUNTRY_CODES = [
  "AD","AE","AF","AG","AI","AL","AM","AO","AQ","AR","AS","AT","AU","AW","AX","AZ",
  "BA","BB","BD","BE","BF","BG","BH","BI","BJ","BL","BM","BN","BO","BQ","BR","BS","BT","BV","BW","BY","BZ",
  "CA","CC","CD","CF","CG","CH","CI","CK","CL","CM","CN","CO","CR","CU","CV","CW","CX","CY","CZ",
  "DE","DJ","DK","DM","DO","DZ",
  "EC","EE","EG","EH","ER","ES","ET",
  "FI","FJ","FK","FM","FO","FR",
  "GA","GB","GD","GE","GF","GG","GH","GI","GL","GM","GN","GP","GQ","GR","GS","GT","GU","GW","GY",
  "HK","HM","HN","HR","HT","HU",
  "ID","IE","IL","IM","IN","IO","IQ","IR","IS","IT",
  "JE","JM","JO","JP",
  "KE","KG","KH","KI","KM","KN","KP","KR","KW","KY","KZ",
  "LA","LB","LC","LI","LK","LR","LS","LT","LU","LV","LY",
  "MA","MC","MD","ME","MF","MG","MH","MK","ML","MM","MN","MO","MP","MQ","MR","MS","MT","MU","MV","MW","MX","MY","MZ",
  "NA","NC","NE","NF","NG","NI","NL","NO","NP","NR","NU","NZ",
  "OM",
  "PA","PE","PF","PG","PH","PK","PL","PM","PN","PR","PS","PT","PW","PY",
  "QA",
  "RE","RO","RS","RU","RW",
  "SA","SB","SC","SD","SE","SG","SH","SI","SJ","SK","SL","SM","SN","SO","SR","SS","ST","SV","SX","SY","SZ",
  "TC","TD","TF","TG","TH","TJ","TK","TL","TM","TN","TO","TR","TT","TV","TW","TZ",
  "UA","UG","UM","US","UY","UZ",
  "VA","VC","VE","VG","VI","VN","VU",
  "WF","WS",
  "YE","YT",
  "ZA","ZM","ZW",
];
