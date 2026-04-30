// Initialize flatpickr for all date inputs
document.querySelectorAll('input[type="date"]').forEach(input => {
    flatpickr(input, {
        dateFormat: "Y-m-d",
        allowInput: true,
        defaultDate: input.value || null,
        minDate: input.min || null
    });
});

const zoomStage = document.querySelector("[data-flightplan-zoom-stage]");
const zoomScrollContainer = document.querySelector(".flightplan-card-scroll");
const zoomReadout = document.querySelector("[data-flightplan-zoom-readout]");
const zoomInButton = document.querySelector("[data-flightplan-zoom-in]");
const zoomOutButton = document.querySelector("[data-flightplan-zoom-out]");
const zoomStorageKey = "flightplan-form-zoom";

if (zoomStage && zoomScrollContainer && zoomReadout && zoomInButton && zoomOutButton) {
    const MIN_ZOOM = 0.25;
    const MAX_ZOOM = 1.25;
    const STEP = 0.1;

    const clampZoom = value => Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value));

    const measureZoomHeight = scale => {
        const card = zoomStage.firstElementChild;

        if (!card) {
            return 0;
        }

        const cardHeight = card.offsetHeight;
        const marginBottom = parseFloat(window.getComputedStyle(card).marginBottom || "0");
        const marginTop = parseFloat(window.getComputedStyle(card).marginTop || "0");

        return (cardHeight + marginTop + marginBottom) * scale;
    };

    const syncZoomLayout = scale => {
        zoomStage.style.transform = `scale(${scale})`;
        zoomStage.style.height = `${measureZoomHeight(scale)}px`;
        zoomReadout.textContent = `${Math.round(scale * 100)}%`;
        window.localStorage.setItem(zoomStorageKey, String(scale));
    };

    const savedZoom = Number.parseFloat(window.localStorage.getItem(zoomStorageKey) || "1");
    let currentZoom = clampZoom(Number.isFinite(savedZoom) ? savedZoom : 1);

    syncZoomLayout(currentZoom);

    zoomInButton.addEventListener("click", () => {
        currentZoom = clampZoom(Number((currentZoom + STEP).toFixed(2)));
        syncZoomLayout(currentZoom);
    });

    zoomOutButton.addEventListener("click", () => {
        currentZoom = clampZoom(Number((currentZoom - STEP).toFixed(2)));
        syncZoomLayout(currentZoom);
    });

    window.addEventListener("resize", () => {
        syncZoomLayout(currentZoom);
    });
}

// Keep time fields as plain 4-digit inputs on the form while the backend stores HH:mm.
document.querySelectorAll('.plain-time-input').forEach(input => {
    input.addEventListener("input", () => {
        input.value = input.value.replace(/\D/g, "").slice(0, 4);
    });
});

// Setup inverted checkbox logic with hidden fields
const invertedCheckboxes = document.querySelectorAll('input[type="checkbox"].inverted-checkbox');
const form = document.getElementById("flightplan-form");
const formStateKey = "flightplan-form-state";
const hasServerProvidedInput = form?.dataset.hasOldInput === "true";
const hasPrefilledInput = form?.dataset.hasPrefilledInput === "true";

function syncInvertedCheckboxValue(checkbox) {
    const hiddenInput = checkbox.parentElement?.querySelector(`input[type="hidden"][name="${checkbox.name}"]`);

    if (hiddenInput) {
        hiddenInput.value = checkbox.checked ? "0" : "1";
    }
}

function resetInvertedCheckboxes() {
    invertedCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
        syncInvertedCheckboxValue(checkbox);
    });
}

// Reuse the paired hidden field for each inverted checkbox instead of creating duplicates.
invertedCheckboxes.forEach(checkbox => {
    let hiddenInput = checkbox.parentElement?.querySelector(`input[type="hidden"][name="${checkbox.name}"]`);

    if (!hiddenInput) {
        hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = checkbox.name;
        checkbox.parentElement?.insertBefore(hiddenInput, checkbox);
    }

    checkbox.dataset.invertedCheckbox = "true";
    hiddenInput.dataset.invertedHidden = "true";
    syncInvertedCheckboxValue(checkbox);

    // Update hidden field when checkbox state changes
    checkbox.addEventListener("change", () => {
        syncInvertedCheckboxValue(checkbox);
    });
});

// Before form submission, disable the visible checkboxes so they don't send their values
form?.addEventListener("submit", () => {
    validateOtherInformationTags();

    invertedCheckboxes.forEach(checkbox => {
        checkbox.disabled = true;
    });
});

function restoreFormState() {
    const savedState = sessionStorage.getItem(formStateKey);

    if (!savedState) {
        return;
    }

    try {
        const parsedState = JSON.parse(savedState);

        Object.entries(parsedState).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);

            if (!field) {
                return;
            }

            if (field instanceof RadioNodeList) {
                const inputs = Array.from(field);
                const checkableInputs = inputs.filter(input => input.type === "checkbox" || input.type === "radio");

                if (checkableInputs.length > 0) {
                    checkableInputs.forEach(input => {
                        input.checked = Array.isArray(value) ? value.includes(input.value) : Boolean(value);
                        input.dispatchEvent(new Event("change", { bubbles: true }));
                    });

                    return;
                }

                inputs.forEach(input => {
                    input.value = typeof value === "string" ? value : "";
                });

                return;
            }

            if (field.type === "checkbox") {
                field.checked = Boolean(value);
                field.dispatchEvent(new Event("change", { bubbles: true }));
                return;
            }

            field.value = typeof value === "string" ? value : "";
            field.dispatchEvent(new Event("input", { bubbles: true }));
            field.dispatchEvent(new Event("change", { bubbles: true }));
        });
    } catch (error) {
        sessionStorage.removeItem(formStateKey);
    }
}

function persistFormState() {
    const formData = new FormData(form);
    const state = {};

    Array.from(form.elements).forEach(field => {
        if (!field.name || field.disabled || field.name === "_token" || field.name === "date_of_filing") {
            return;
        }

        if (field.dataset?.invertedHidden === "true") {
            return;
        }

        if (field.type === "checkbox") {
            state[field.name] = field.checked;
            return;
        }

        if (field.type === "radio") {
            if (field.checked) {
                state[field.name] = field.value;
            }
            return;
        }

        if (formData.has(field.name)) {
            state[field.name] = field.value;
        }
    });

    sessionStorage.setItem(formStateKey, JSON.stringify(state));
}

form?.addEventListener("input", persistFormState);
form?.addEventListener("change", persistFormState);
form?.addEventListener("reset", () => {
    sessionStorage.removeItem(formStateKey);
});

// Handle Dinghies checkbox and field logic
const dinghiesCheckbox = document.getElementById("dinghies-checkbox");
const dinghiesFields = document.querySelectorAll(".dinghies-field");

if (dinghiesCheckbox) {
    dinghiesCheckbox.addEventListener("change", () => {
        const isChecked = dinghiesCheckbox.checked; // true = X mark = disabled, false = unchecked = enabled
        dinghiesFields.forEach(field => {
            if (isChecked) {
                // When checked (X mark), disable fields and remove required
                field.disabled = true;
                field.removeAttribute("required");
                field.value = "";
            } else {
                // When unchecked, enable fields and make required
                field.disabled = false;
                field.setAttribute("required", "required");
            }
        });
    });

    // Initialize dinghies fields from the server-rendered checkbox state.
    dinghiesCheckbox.dispatchEvent(new Event("change", { bubbles: true }));
}

const departureAerodromeInput = form.querySelector('input[name="departure_aerodrome"]');
const destinationAerodromeInput = form.querySelector('input[name="destination_aerodrome"]');
const typOfAircraftInput = form.querySelector('input[name="type_of_aircraft"]');
const wakeTurbulenceCatInput = form.querySelector('input[name="wake_turbulence_cat"]');
const altnInput = form.querySelector('input[name="altn_aerodrome_1"]');
const altn2Input = form.querySelector('input[name="altn_aerodrome_2"]');
const dateOfFlightInput = form.querySelector('input[name="date_of_flight"]');
const otherInformationField = form.querySelector('textarea[name="other_information"]');
const otherInfoHint = document.getElementById('other-info-hint');
const aircraftWtcMap = window.flightplanAircraftWtcMap ?? {};

const syncWakeTurbulenceCategory = () => {
    if (!typOfAircraftInput || !wakeTurbulenceCatInput) {
        return;
    }

    const designator = typOfAircraftInput.value.trim().toUpperCase();

    if (!designator || designator === 'ZZZZ') {
        return;
    }

    const wakeTurbulenceCategory = aircraftWtcMap[designator];

    if (!wakeTurbulenceCategory) {
        return;
    }

    wakeTurbulenceCatInput.value = wakeTurbulenceCategory;
    wakeTurbulenceCatInput.dispatchEvent(new Event('input', { bubbles: true }));
    wakeTurbulenceCatInput.dispatchEvent(new Event('change', { bubbles: true }));
};

// Auto-insert DOF/ tag with date_of_flight value
const updateDofTag = () => {
    if (!dateOfFlightInput || !otherInformationField) {
        return;
    }

    const dateValue = dateOfFlightInput.value;
    if (!dateValue) {
        return;
    }

    // Use the raw picker value so the DOF tag matches the selected UTC date exactly.
    const formattedDate = dateValue.replace(/-/g, '');

    const dofTag = 'DOF/' + formattedDate;
    const currentValue = otherInformationField.value || '';
    const escapedDofTag = dofTag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const dofPattern = new RegExp('DOF/[^\\s]*', 'i');

    if (dofPattern.test(currentValue)) {
        // Replace existing DOF tag
        otherInformationField.value = currentValue.replace(dofPattern, dofTag);
    } else {
        // Add DOF tag at the end
        const newValue = currentValue.trim() + (currentValue ? ' ' : '') + dofTag;
        otherInformationField.value = newValue.trim();
    }

    otherInformationField.dispatchEvent(new Event('input', { bubbles: true }));
};

// Initialize DOF tag on page load
updateDofTag();

// Update DOF tag when date changes
dateOfFlightInput?.addEventListener('change', updateDofTag);

const updateOtherInformationRequirements = () => {
    const departureIsZzzz = departureAerodromeInput?.value.trim().toUpperCase() === 'ZZZZ';
    const destinationIsZzzz = destinationAerodromeInput?.value.trim().toUpperCase() === 'ZZZZ';
    const typOfAircraftIsZzzz = typOfAircraftInput?.value.trim().toUpperCase() === 'ZZZZ';
    const altnIsIsZzzz = altnInput?.value.trim().toUpperCase() === 'ZZZZ';
    const altn2IsZzzz = altn2Input?.value.trim().toUpperCase() === 'ZZZZ';
    const isRequired = departureIsZzzz || destinationIsZzzz || typOfAircraftIsZzzz || altnIsIsZzzz || altn2IsZzzz;

    if (!otherInformationField) {
        return;
    }

    if (isRequired) {
        otherInformationField.setAttribute('required', 'required');
    } else {
        otherInformationField.removeAttribute('required');
        otherInformationField.setCustomValidity('');
    }

    if (!otherInfoHint) {
        return;
    }

    const hintMessages = [];

    if (departureIsZzzz) {
        hintMessages.push('Include DEP/ in Other Information when departure aerodrome is ZZZZ.');
    }

    if (destinationIsZzzz) {
        hintMessages.push('Include DEST/ in Other Information when destination aerodrome is ZZZZ.');
    }

    if (typOfAircraftIsZzzz) {
        hintMessages.push('Include TYP/ in Other Information when Type of Aircraft is ZZZZ.');
    }

    if (altnIsIsZzzz) {
        hintMessages.push('Include ALTN/ in Other Information when alternate aerodrome is ZZZZ.');
    }

    if (altn2IsZzzz) {
        hintMessages.push('Include ALTN2/ in Other Information when 2nd alternate aerodrome is ZZZZ.');
    }

    otherInfoHint.textContent = hintMessages.join(' ');
    otherInfoHint.classList.toggle('hidden', hintMessages.length === 0);
};

const autoInsertMissingTags = (requiredTags) => {
    const value = otherInformationField.value || '';
    let newValue = value;

    requiredTags.forEach(tag => {
        const validTagPattern = new RegExp(tag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.{4,}', 'i');
        if (validTagPattern.test(newValue)) {
            return;
        }

        const escapedTag = tag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const existingTagPattern = new RegExp(`${escapedTag}[^\s]*`, 'i');

        if (existingTagPattern.test(newValue)) {
            // Tag already exists, even if incomplete; leave it alone so the user can continue typing.
            return;
        }

        if (newValue && !newValue.endsWith(' ')) {
            newValue += ' ';
        }
        newValue += tag;
    });

    otherInformationField.value = newValue.trim();
    otherInformationField.dispatchEvent(new Event('input', { bubbles: true }));
};

const validateOtherInformationTags = () => {
    if (!otherInformationField) {
        return;
    }

    const value = otherInformationField.value || '';
    let errors = [];
    const requiredTags = [];

    if (departureAerodromeInput?.value.trim().toUpperCase() === 'ZZZZ') {
        if (!/DEP\/.{4,}/i.test(value)) {
            errors.push('When departure aerodrome is ZZZZ, Other Information must include DEP/.');
            requiredTags.push('DEP/');
        }
    }

    if (destinationAerodromeInput?.value.trim().toUpperCase() === 'ZZZZ') {
        if (!/DEST\/.{4,}/i.test(value)) {
            errors.push('When destination aerodrome is ZZZZ, Other Information must include DEST/.');
            requiredTags.push('DEST/');
        }
    }

    if (typOfAircraftInput?.value.trim().toUpperCase() === 'ZZZZ') {
        if (!/TYP\/.{4,}/i.test(value)) {
            errors.push('When Type of Aircraft is ZZZZ, Other Information must include TYP/.');
            requiredTags.push('TYP/');
        }
    }

    if (altnInput?.value.trim().toUpperCase() === 'ZZZZ') {
        if (!/ALTN\/.{4,}/i.test(value)) {
            errors.push('When alternate aerodrome is ZZZZ, Other Information must include ALTN/.');
            requiredTags.push('ALTN/');
        }
    }

    if (altn2Input?.value.trim().toUpperCase() === 'ZZZZ') {
        if (!/ALTN2\/.{4,}/i.test(value)) {
            errors.push('When 2nd alternate aerodrome is ZZZZ, Other Information must include ALTN2/.');
            requiredTags.push('ALTN2/');
        }
    }

    // Auto-insert missing tags immediately when they're needed
    if (errors.length > 0 && requiredTags.length > 0) {
        autoInsertMissingTags(requiredTags);
    }

    otherInformationField.setCustomValidity(errors.join(' '));
};

if (departureAerodromeInput && destinationAerodromeInput && otherInformationField) {
    
    departureAerodromeInput.addEventListener('input', () => {
        updateOtherInformationRequirements();
        validateOtherInformationTags();
    });

    destinationAerodromeInput.addEventListener('input', () => {
        updateOtherInformationRequirements();
        validateOtherInformationTags();
    });

    typOfAircraftInput?.addEventListener('input', () => {
        updateOtherInformationRequirements();
        validateOtherInformationTags();
    });

    typOfAircraftInput?.addEventListener('blur', syncWakeTurbulenceCategory);
    typOfAircraftInput?.addEventListener('change', syncWakeTurbulenceCategory);

    altnInput?.addEventListener('input', () => {
        updateOtherInformationRequirements();
        validateOtherInformationTags();
    });

    altn2Input?.addEventListener('input', () => {
        updateOtherInformationRequirements();
        validateOtherInformationTags();
    });

    otherInformationField.addEventListener('input', validateOtherInformationTags);

    updateOtherInformationRequirements();
    syncWakeTurbulenceCategory();
}

// Handle Authorized Representative collapsible section
const authRepCheckbox = document.getElementById("authorized-rep-checkbox");
const authRepPanel = document.getElementById("authorized-rep-panel");
const authRepContent = document.getElementById("authorized-rep-content");
const authRepFields = document.querySelectorAll(".authorized-rep-field");
const authRepEnabled = document.getElementById("authorized-rep-enabled");
const authRepNameInput = document.querySelector('input[name="authorized_representative_name"]');
const authRepRoleInput = document.querySelector('input[name="authorized_representative_role"]');
const authRepIdInput = document.querySelector('input[name="authorized_representative_id_license"]');
const authRepExpiryInput = document.querySelector('input[name="authorized_representative_expiry_date"]');

const setAuthorizedRepState = isEnabled => {
    if (authRepEnabled) {
        authRepEnabled.value = isEnabled ? "1" : "0";
    }
    authRepPanel.classList.toggle("collapsible-disabled", !isEnabled);
    authRepContent.classList.toggle("hidden", !isEnabled);

    authRepFields.forEach(field => {
        field.disabled = !isEnabled;
        if (!isEnabled) {
            field.removeAttribute("required");
            field.value = "";
        }
    });

    if (isEnabled) {
        // Required when dispatch filing is enabled.
        authRepNameInput?.setAttribute("required", "required");
        authRepIdInput?.setAttribute("required", "required");
        authRepExpiryInput?.setAttribute("required", "required");
        authRepRoleInput?.removeAttribute("required");
    } else {
        authRepNameInput?.removeAttribute("required");
        authRepIdInput?.removeAttribute("required");
        authRepExpiryInput?.removeAttribute("required");
        authRepRoleInput?.removeAttribute("required");
    }
};

if (authRepCheckbox && authRepPanel && authRepContent) {
    setAuthorizedRepState(!authRepCheckbox.checked);

    authRepCheckbox.addEventListener("change", () => {
        setAuthorizedRepState(!authRepCheckbox.checked);
        syncCertificationLines();
    });
}

// Auto-copy data into certification signature lines
const pilotNameInput = document.querySelector('input[name="pilot_in_command"]');
const pilotLicenseInput = document.querySelector('input[name="pilot_license_no"]');
const pilotRatingsInput = document.querySelector('input[name="pilot_ratings"]');
const pilotExpiryInput = document.querySelector('input[name="license_expiry_date"]');

const certPilotSignatureValue = document.getElementById("cert-pilot-signature-value");
const certPilotLicenseValue = document.getElementById("cert-pilot-license-value");
const certAuthRepValue = document.getElementById("cert-auth-rep-value");
const certAuthLicenseValue = document.getElementById("cert-auth-license-value");

function buildJoinedValue(parts) {
    return parts.filter(Boolean).join(" / ");
}

function syncCertificationLines() {
    const pilotName = pilotNameInput?.value.trim() || "";
    const pilotLicense = pilotLicenseInput?.value.trim() || "";
    const pilotRatings = pilotRatingsInput?.value.trim() || "";
    const pilotExpiry = pilotExpiryInput?.value.trim() || "";

    const authRepName = authRepNameInput?.value.trim() || "";
    const authRepId = authRepIdInput?.value.trim() || "";
    const authRepExpiry = authRepExpiryInput?.value.trim() || "";

    certPilotSignatureValue.textContent = pilotName;
    certPilotLicenseValue.textContent = buildJoinedValue([pilotLicense, pilotRatings, pilotExpiry]);
    certAuthRepValue.textContent = authRepName;
    certAuthLicenseValue.textContent = buildJoinedValue([authRepId, authRepExpiry]);
}

[
    pilotNameInput,
    pilotLicenseInput,
    pilotRatingsInput,
    pilotExpiryInput,
    authRepNameInput,
    authRepRoleInput,
    authRepIdInput,
    authRepExpiryInput
].forEach(field => {
    if (!field) return;
    field.addEventListener("input", syncCertificationLines);
    field.addEventListener("change", syncCertificationLines);
});

syncCertificationLines();

const navigationEntry = performance.getEntriesByType("navigation")[0];
const isReloadOrBackForward = navigationEntry && ["reload", "back_forward"].includes(navigationEntry.type);

if (isReloadOrBackForward && !hasServerProvidedInput && !hasPrefilledInput) {
    resetInvertedCheckboxes();
}

window.addEventListener("pageshow", event => {
    if (event.persisted && !hasServerProvidedInput && !hasPrefilledInput) {
        resetInvertedCheckboxes();
    }
});

// Dismissible alerts
document.querySelectorAll(".flightplan-alert-close").forEach(button => {
    button.addEventListener("click", () => {
        const targetId = button.getAttribute("data-alert-target");
        const alert = targetId ? document.getElementById(targetId) : null;

        if (alert) {
            alert.remove();
        }
    });
});

// Auto-hide success message after 12 seconds
const successAlert = document.getElementById("flightplan-success-alert");
const discardAlert = document.getElementById("flightplan-discard-alert");

if (successAlert) {
    window.setTimeout(() => {
        successAlert.remove();
    }, 12000);
}

if (successAlert || discardAlert || hasServerProvidedInput || hasPrefilledInput) {
    sessionStorage.removeItem(formStateKey);
} else {
    restoreFormState();
    syncCertificationLines();
}

/*formart time input as HH:mm
function formatTime(input) {
  let value = input.value.replace(/\D/g, ''); // keep only digits
  if (value.length === 4) {
    input.value = value.slice(0,2) + ':' + value.slice(2);
  }
}*/
