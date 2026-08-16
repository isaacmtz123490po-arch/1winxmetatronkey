use std::net::IpAddr;
use std::str::FromStr;

use der::asn1::{GeneralizedTime, Ia5String, Ia5StringRef, OctetString, UtcTime};
use der::pem::LineEnding;
use der::{DateTime, Decode, DecodePem, EncodePem};
use js_sys::Array;
use p256::ecdsa::{DerSignature, SigningKey};
use p256::pkcs8::{DecodePrivateKey, EncodePrivateKey};
use rand_core::OsRng;
use spki::{EncodePublicKey, SubjectPublicKeyInfoOwned};
use time::{Date, Duration, Month, OffsetDateTime, PrimitiveDateTime};
use wasm_bindgen::JsCast;
use wasm_bindgen_futures::{spawn_local, JsFuture};
use web_sys::{
    Blob, BlobPropertyBag, Event, HtmlAnchorElement, HtmlInputElement, InputEvent, KeyboardEvent,
    MouseEvent, Url,
};
use x509_cert::builder::{Builder, CertificateBuilder, Profile};
use x509_cert::ext::pkix::{name::GeneralName, SubjectAltName};
use x509_cert::name::Name;
use x509_cert::serial_number::SerialNumber;
use x509_cert::time::{Time, Validity};
use x509_cert::Certificate;
use yew::prelude::*;

// ── Mode ──────────────────────────────────────────────────────────────────────

#[derive(Clone, PartialEq)]
enum CertMode {
    SelfSigned,
    SelfSignedCa,
    CaSigned,
}

// ── Helpers ───────────────────────────────────────────────────────────────────

fn to_der_time(ts: i64) -> Result<Time, String> {
    let secs = ts.max(0) as u64;
    let dt = DateTime::from_unix_duration(core::time::Duration::from_secs(secs))
        .map_err(|e| e.to_string())?;
    if let Ok(utc) = UtcTime::from_date_time(dt) {
        Ok(Time::from(utc))
    } else {
        Ok(Time::from(GeneralizedTime::from_date_time(dt)))
    }
}

fn parse_date_str(s: &str) -> Result<OffsetDateTime, String> {
    let parts: Vec<&str> = s.splitn(3, '-').collect();
    if parts.len() != 3 {
        return Err("Expected YYYY-MM-DD".to_string());
    }
    let year: i32 = parts[0].parse().map_err(|_| "Invalid year".to_string())?;
    let month_n: u8 = parts[1].parse().map_err(|_| "Invalid month".to_string())?;
    let day: u8 = parts[2].parse().map_err(|_| "Invalid day".to_string())?;
    let month = Month::try_from(month_n).map_err(|_| "Month must be 1–12".to_string())?;
    let date = Date::from_calendar_date(year, month, day).map_err(|e| e.to_string())?;
    Ok(PrimitiveDateTime::new(date, time::Time::MIDNIGHT).assume_utc())
}

fn build_subject(cn: &str) -> Result<Name, String> {
    let name_str = if cn.is_empty() {
        "CN=Unknown".to_string()
    } else {
        let escaped = cn
            .replace('\\', "\\\\")
            .replace('+', "\\+")
            .replace(',', "\\,")
            .replace(';', "\\;")
            .replace('<', "\\<")
            .replace('>', "\\>")
            .replace('=', "\\=")
            .replace('#', "\\#");
        format!("CN={escaped}")
    };
    name_str.parse().map_err(|e| format!("Invalid CN: {e}"))
}

fn random_serial() -> Result<SerialNumber, String> {
    let mut bytes = [0u8; 20];
    getrandom::getrandom(&mut bytes).map_err(|e| e.to_string())?;
    bytes[0] &= 0x7f; // ensure positive integer
    SerialNumber::new(&bytes).map_err(|e| e.to_string())
}

fn build_san_gns(sans: &[String]) -> Result<Vec<GeneralName>, String> {
    let mut gns = Vec::new();
    for s in sans {
        if let Ok(ip) = IpAddr::from_str(s) {
            let bytes: Vec<u8> = match ip {
                IpAddr::V4(v4) => v4.octets().into(),
                IpAddr::V6(v6) => v6.octets().into(),
            };
            gns.push(GeneralName::IpAddress(
                OctetString::new(bytes).map_err(|e| e.to_string())?,
            ));
        } else {
            let ia5 = Ia5String::from(
                Ia5StringRef::new(s.as_str()).map_err(|e| e.to_string())?,
            );
            gns.push(GeneralName::DnsName(ia5));
        }
    }
    Ok(gns)
}

// ── Certificate generation ────────────────────────────────────────────────────

/// Generates a self-signed leaf certificate or a self-signed CA certificate.
fn generate_self_cert(
    cn: &str,
    sans: &[String],
    not_before: OffsetDateTime,
    not_after: OffsetDateTime,
    is_ca: bool,
) -> Result<(String, String), String> {
    let signing_key = SigningKey::random(&mut OsRng);

    let pub_der = signing_key
        .verifying_key()
        .to_public_key_der()
        .map_err(|e| e.to_string())?;
    let spki = SubjectPublicKeyInfoOwned::from_der(pub_der.as_bytes())
        .map_err(|e| e.to_string())?;

    let subject = build_subject(cn)?;
    let validity = Validity {
        not_before: to_der_time(not_before.unix_timestamp())?,
        not_after: to_der_time(not_after.unix_timestamp())?,
    };

    let profile = if is_ca {
        Profile::Root
    } else {
        Profile::Leaf {
            issuer: subject.clone(),
            enable_key_agreement: false,
            enable_key_encipherment: true,
        }
    };

    let mut builder = CertificateBuilder::new(
        profile,
        random_serial()?,
        validity,
        subject,
        spki,
        &signing_key,
    )
    .map_err(|e| e.to_string())?;

    if !sans.is_empty() {
        let gns = build_san_gns(sans)?;
        builder
            .add_extension(&SubjectAltName(gns))
            .map_err(|e| e.to_string())?;
    }

    let cert = builder.build::<DerSignature>().map_err(|e| e.to_string())?;
    let cert_pem = cert.to_pem(LineEnding::LF).map_err(|e| e.to_string())?;
    let key_pem = signing_key
        .to_pkcs8_pem(pkcs8::LineEnding::LF)
        .map_err(|e| e.to_string())?
        .to_string();

    Ok((cert_pem, key_pem))
}

/// Generates a leaf certificate signed by the provided CA cert + key (PEM).
/// Returns (leaf_cert_pem, leaf_key_pem).
fn generate_ca_signed(
    cn: &str,
    sans: &[String],
    not_before: OffsetDateTime,
    not_after: OffsetDateTime,
    ca_cert_pem: &str,
    ca_key_pem: &str,
) -> Result<(String, String), String> {
    // Parse CA cert to extract the issuer name
    let ca_cert = Certificate::from_pem(ca_cert_pem.as_bytes())
        .map_err(|e| format!("Invalid CA certificate: {e}"))?;
    let issuer = ca_cert.tbs_certificate.subject.clone();

    // Parse CA signing key
    let ca_signing_key = SigningKey::from_pkcs8_pem(ca_key_pem)
        .map_err(|e| format!("Invalid CA private key: {e}"))?;

    // Generate a fresh key pair for the leaf certificate
    let leaf_key = SigningKey::random(&mut OsRng);

    let pub_der = leaf_key
        .verifying_key()
        .to_public_key_der()
        .map_err(|e| e.to_string())?;
    let spki = SubjectPublicKeyInfoOwned::from_der(pub_der.as_bytes())
        .map_err(|e| e.to_string())?;

    let subject = build_subject(cn)?;
    let validity = Validity {
        not_before: to_der_time(not_before.unix_timestamp())?,
        not_after: to_der_time(not_after.unix_timestamp())?,
    };

    let mut builder = CertificateBuilder::new(
        Profile::Leaf {
            issuer,
            enable_key_agreement: false,
            enable_key_encipherment: true,
        },
        random_serial()?,
        validity,
        subject,
        spki,
        &ca_signing_key, // signed by CA
    )
    .map_err(|e| e.to_string())?;

    if !sans.is_empty() {
        let gns = build_san_gns(sans)?;
        builder
            .add_extension(&SubjectAltName(gns))
            .map_err(|e| e.to_string())?;
    }

    let cert = builder.build::<DerSignature>().map_err(|e| e.to_string())?;
    let cert_pem = cert.to_pem(LineEnding::LF).map_err(|e| e.to_string())?;
    let key_pem = leaf_key
        .to_pkcs8_pem(pkcs8::LineEnding::LF)
        .map_err(|e| e.to_string())?
        .to_string();

    Ok((cert_pem, key_pem))
}

// ── File download ─────────────────────────────────────────────────────────────

fn trigger_download(content: &str, filename: &str) {
    let window = web_sys::window().unwrap();
    let document = window.document().unwrap();

    let array = Array::new();
    array.push(&wasm_bindgen::JsValue::from_str(content));
    let opts = BlobPropertyBag::new();
    opts.set_type("application/x-pem-file");
    let blob = Blob::new_with_str_sequence_and_options(&array, &opts).unwrap();
    let url = Url::create_object_url_with_blob(&blob).unwrap();

    let anchor: HtmlAnchorElement = document.create_element("a").unwrap().dyn_into().unwrap();
    anchor.set_href(&url);
    anchor.set_download(filename);

    let body = document.body().unwrap();
    body.append_child(&anchor).unwrap();
    anchor.click();
    body.remove_child(&anchor).unwrap();

    Url::revoke_object_url(&url).unwrap();
}

// ── Component ─────────────────────────────────────────────────────────────────

#[function_component]
fn App() -> Html {
    let cert_mode = use_state(|| CertMode::SelfSigned);

    // Leaf cert fields
    let cn = use_state(String::new);
    let san_input = use_state(String::new);
    let sans = use_state(Vec::<String>::new);
    let use_relative = use_state(|| true);
    let expiry_days = use_state(|| "365".to_string());
    let expiry_date = use_state(String::new);
    let prefix = use_state(|| "mycert".to_string());

    // CA-signed inputs (file contents + display names)
    let ca_cert_input = use_state(String::new);
    let ca_cert_name = use_state(String::new);
    let ca_key_input = use_state(String::new);
    let ca_key_name = use_state(String::new);

    // Outputs
    let cert_pem = use_state(|| Option::<String>::None);
    let key_pem = use_state(|| Option::<String>::None);
    // For CA-signed mode: concatenated cert chain (leaf + CA)
    let chain_pem = use_state(|| Option::<String>::None);
    let error = use_state(|| Option::<String>::None);

    // ── Mode tabs ─────────────────────────────────────
    let on_mode_self = {
        let cert_mode = cert_mode.clone();
        let cert_pem = cert_pem.clone(); let key_pem = key_pem.clone();
        let chain_pem = chain_pem.clone(); let error = error.clone();
        Callback::from(move |_: MouseEvent| {
            cert_mode.set(CertMode::SelfSigned);
            cert_pem.set(None); key_pem.set(None); chain_pem.set(None); error.set(None);
        })
    };
    let on_mode_ca = {
        let cert_mode = cert_mode.clone();
        let cert_pem = cert_pem.clone(); let key_pem = key_pem.clone();
        let chain_pem = chain_pem.clone(); let error = error.clone();
        Callback::from(move |_: MouseEvent| {
            cert_mode.set(CertMode::SelfSignedCa);
            cert_pem.set(None); key_pem.set(None); chain_pem.set(None); error.set(None);
        })
    };
    let on_mode_signed = {
        let cert_mode = cert_mode.clone();
        let cert_pem = cert_pem.clone(); let key_pem = key_pem.clone();
        let chain_pem = chain_pem.clone(); let error = error.clone();
        Callback::from(move |_: MouseEvent| {
            cert_mode.set(CertMode::CaSigned);
            cert_pem.set(None); key_pem.set(None); chain_pem.set(None); error.set(None);
        })
    };

    // ── CN ────────────────────────────────────────────
    let on_cn = {
        let cn = cn.clone();
        Callback::from(move |e: InputEvent| {
            let input: HtmlInputElement = e.target_unchecked_into();
            cn.set(input.value());
        })
    };

    // ── SAN ───────────────────────────────────────────
    let on_san_input = {
        let san_input = san_input.clone();
        Callback::from(move |e: InputEvent| {
            let input: HtmlInputElement = e.target_unchecked_into();
            san_input.set(input.value());
        })
    };

    let push_san_btn = {
        let san_input = san_input.clone();
        let sans = sans.clone();
        Callback::from(move |_: MouseEvent| {
            let val = san_input.trim().to_string();
            if !val.is_empty() {
                let mut v = (*sans).clone();
                if !v.contains(&val) { v.push(val); sans.set(v); }
                san_input.set(String::new());
            }
        })
    };

    let push_san_key = {
        let san_input = san_input.clone();
        let sans = sans.clone();
        Callback::from(move |e: KeyboardEvent| {
            if e.key() == "Enter" {
                let val = san_input.trim().to_string();
                if !val.is_empty() {
                    let mut v = (*sans).clone();
                    if !v.contains(&val) { v.push(val); sans.set(v); }
                    san_input.set(String::new());
                }
            }
        })
    };

    // ── Expiry ────────────────────────────────────────
    let on_use_relative = {
        let use_relative = use_relative.clone();
        Callback::from(move |_: MouseEvent| use_relative.set(true))
    };
    let on_use_absolute = {
        let use_relative = use_relative.clone();
        Callback::from(move |_: MouseEvent| use_relative.set(false))
    };
    let on_days = {
        let expiry_days = expiry_days.clone();
        Callback::from(move |e: InputEvent| {
            let input: HtmlInputElement = e.target_unchecked_into();
            expiry_days.set(input.value());
        })
    };
    let on_date = {
        let expiry_date = expiry_date.clone();
        Callback::from(move |e: Event| {
            let input: HtmlInputElement = e.target_unchecked_into();
            expiry_date.set(input.value());
        })
    };

    // ── Prefix ────────────────────────────────────────
    let on_prefix = {
        let prefix = prefix.clone();
        Callback::from(move |e: InputEvent| {
            let input: HtmlInputElement = e.target_unchecked_into();
            prefix.set(input.value());
        })
    };

    // ── CA file pickers ───────────────────────────────
    let on_ca_cert_file = {
        let ca_cert_input = ca_cert_input.clone();
        let ca_cert_name = ca_cert_name.clone();
        Callback::from(move |e: Event| {
            let input: HtmlInputElement = e.target_unchecked_into();
            if let Some(files) = input.files() {
                if let Some(file) = files.get(0) {
                    let filename = file.name();
                    let content_st = ca_cert_input.clone();
                    let name_st = ca_cert_name.clone();
                    let promise = AsRef::<web_sys::Blob>::as_ref(&file).text();
                    spawn_local(async move {
                        if let Ok(val) = JsFuture::from(promise).await {
                            content_st.set(val.as_string().unwrap_or_default());
                            name_st.set(filename);
                        }
                    });
                }
            }
        })
    };
    let clear_ca_cert = {
        let ca_cert_input = ca_cert_input.clone();
        let ca_cert_name = ca_cert_name.clone();
        Callback::from(move |_: MouseEvent| {
            ca_cert_input.set(String::new());
            ca_cert_name.set(String::new());
        })
    };
    let on_ca_key_file = {
        let ca_key_input = ca_key_input.clone();
        let ca_key_name = ca_key_name.clone();
        Callback::from(move |e: Event| {
            let input: HtmlInputElement = e.target_unchecked_into();
            if let Some(files) = input.files() {
                if let Some(file) = files.get(0) {
                    let filename = file.name();
                    let content_st = ca_key_input.clone();
                    let name_st = ca_key_name.clone();
                    let promise = AsRef::<web_sys::Blob>::as_ref(&file).text();
                    spawn_local(async move {
                        if let Ok(val) = JsFuture::from(promise).await {
                            content_st.set(val.as_string().unwrap_or_default());
                            name_st.set(filename);
                        }
                    });
                }
            }
        })
    };
    let clear_ca_key = {
        let ca_key_input = ca_key_input.clone();
        let ca_key_name = ca_key_name.clone();
        Callback::from(move |_: MouseEvent| {
            ca_key_input.set(String::new());
            ca_key_name.set(String::new());
        })
    };

    // ── Generate ──────────────────────────────────────
    let on_generate = {
        let cert_mode = cert_mode.clone();
        let cn = cn.clone();
        let sans = sans.clone();
        let use_relative = use_relative.clone();
        let expiry_days = expiry_days.clone();
        let expiry_date = expiry_date.clone();
        let prefix = prefix.clone();
        let ca_cert_input = ca_cert_input.clone();
        let ca_key_input = ca_key_input.clone();
        let cert_pem = cert_pem.clone();
        let key_pem = key_pem.clone();
        let chain_pem = chain_pem.clone();
        let error = error.clone();

        Callback::from(move |_: MouseEvent| {
            error.set(None);
            cert_pem.set(None);
            key_pem.set(None);
            chain_pem.set(None);

            if (*cn).is_empty() && (*sans).is_empty() {
                error.set(Some(
                    "Enter at least a Common Name or one Subject Alternative Name.".to_string(),
                ));
                return;
            }
            if (*prefix).is_empty() {
                error.set(Some("File name prefix must not be empty.".to_string()));
                return;
            }

            // Validate CA inputs for CA-signed mode
            if *cert_mode == CertMode::CaSigned {
                if (*ca_cert_input).trim().is_empty() {
                    error.set(Some("Paste the CA certificate PEM.".to_string()));
                    return;
                }
                if (*ca_key_input).trim().is_empty() {
                    error.set(Some("Paste the CA private key PEM.".to_string()));
                    return;
                }
            }

            let now = OffsetDateTime::now_utc();

            let (not_before, not_after) = if *use_relative {
                match (*expiry_days).trim().parse::<i64>() {
                    Err(_) => {
                        error.set(Some("Days must be an integer, e.g. 365 or -30.".to_string()));
                        return;
                    }
                    Ok(days) => {
                        let not_after = now + Duration::days(days);
                        let not_before = if days < 0 {
                            not_after - Duration::days(365)
                        } else {
                            now - Duration::days(1)
                        };
                        (not_before, not_after)
                    }
                }
            } else {
                match parse_date_str((*expiry_date).trim()) {
                    Err(e) => {
                        error.set(Some(format!("Invalid expiry date: {e}")));
                        return;
                    }
                    Ok(not_after) => (now - Duration::days(1), not_after),
                }
            };

            let result = match *cert_mode {
                CertMode::SelfSigned => {
                    generate_self_cert(&*cn, &*sans, not_before, not_after, false)
                }
                CertMode::SelfSignedCa => {
                    generate_self_cert(&*cn, &*sans, not_before, not_after, true)
                }
                CertMode::CaSigned => generate_ca_signed(
                    &*cn,
                    &*sans,
                    not_before,
                    not_after,
                    (*ca_cert_input).trim(),
                    (*ca_key_input).trim(),
                ),
            };

            match result {
                Ok((cert, key)) => {
                    // Build chain for CA-signed mode: leaf cert + CA cert
                    if *cert_mode == CertMode::CaSigned {
                        let chain = format!("{}{}", cert, *ca_cert_input);
                        chain_pem.set(Some(chain));
                    }
                    cert_pem.set(Some(cert));
                    key_pem.set(Some(key));
                }
                Err(e) => error.set(Some(format!("Certificate generation failed: {e}"))),
            }
        })
    };

    // ── Downloads ─────────────────────────────────────
    let on_download_cert = {
        let cert_pem = cert_pem.clone();
        let prefix = prefix.clone();
        Callback::from(move |_: MouseEvent| {
            if let Some(pem) = &*cert_pem {
                trigger_download(pem, &format!("{}_certificate.pem", *prefix));
            }
        })
    };
    let on_download_key = {
        let key_pem = key_pem.clone();
        let prefix = prefix.clone();
        Callback::from(move |_: MouseEvent| {
            if let Some(pem) = &*key_pem {
                trigger_download(pem, &format!("{}_privatekey.pem", *prefix));
            }
        })
    };
    let on_download_chain = {
        let chain_pem = chain_pem.clone();
        let prefix = prefix.clone();
        Callback::from(move |_: MouseEvent| {
            if let Some(pem) = &*chain_pem {
                trigger_download(pem, &format!("{}_chain.pem", *prefix));
            }
        })
    };

    // ── Render ────────────────────────────────────────
    let mode = (*cert_mode).clone();
    let is_relative = *use_relative;
    let has_result = cert_pem.is_some();
    let has_chain = chain_pem.is_some();
    let pfx = (*prefix).clone();

    let title = match mode {
        CertMode::SelfSigned => "Self-Signed Certificate Generator",
        CertMode::SelfSignedCa => "Self-Signed CA Certificate Generator",
        CertMode::CaSigned => "CA-Signed Certificate Generator",
    };

    let btn_label = match mode {
        CertMode::SelfSigned => "Generate Self-Signed Certificate",
        CertMode::SelfSignedCa => "Generate CA Certificate",
        CertMode::CaSigned => "Generate CA-Signed Certificate",
    };

    let cert_label = match mode {
        CertMode::SelfSignedCa => "CA Certificate",
        _ => "Certificate",
    };

    html! {
        <div class="container">
            <h1>{title}</h1>

            // ── Mode tabs ────────────────────────────
            <div class="tabs">
                <button
                    class={if mode == CertMode::SelfSigned { "tab-btn active" } else { "tab-btn" }}
                    onclick={on_mode_self}>
                    {"Self-Signed Cert"}
                </button>
                <button
                    class={if mode == CertMode::SelfSignedCa { "tab-btn active" } else { "tab-btn" }}
                    onclick={on_mode_ca}>
                    {"Self-Signed CA"}
                </button>
                <button
                    class={if mode == CertMode::CaSigned { "tab-btn active" } else { "tab-btn" }}
                    onclick={on_mode_signed}>
                    {"CA-Signed Cert"}
                </button>
            </div>

            // ── Common Name ──────────────────────────
            <div class="form-group">
                <label for="cn">{"Common Name (CN)"}</label>
                <input id="cn" type="text" class="input-field"
                    value={(*cn).clone()}
                    oninput={on_cn}
                    placeholder="e.g. example.com" />
            </div>

            // ── SANs ─────────────────────────────────
            <div class="form-group">
                <label>{"Subject Alternative Names"}</label>
                <div class="san-row">
                    <input type="text" class="input-field"
                        value={(*san_input).clone()}
                        oninput={on_san_input}
                        onkeydown={push_san_key}
                        placeholder="DNS name or IP address — Enter or click Add" />
                    <button class="btn-secondary" onclick={push_san_btn}>{"Add"}</button>
                </div>
                if !(*sans).is_empty() {
                    <ul class="san-list">
                        { for (*sans).iter().enumerate().map(|(i, s)| {
                            let sans2 = sans.clone();
                            let rm = Callback::from(move |_: MouseEvent| {
                                let mut v = (*sans2).clone();
                                v.remove(i);
                                sans2.set(v);
                            });
                            html! {
                                <li key={s.clone()}>
                                    <span class="mono">{s}</span>
                                    <button class="btn-remove" onclick={rm}>{"✕"}</button>
                                </li>
                            }
                        }) }
                    </ul>
                }
            </div>

            // ── Expiry ───────────────────────────────
            <div class="form-group">
                <label>{"Expiry"}</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="expiry_mode"
                            checked={is_relative}
                            onclick={on_use_relative} />
                        <span>{"Days from now:"}</span>
                        <input type="number" class="input-short"
                            value={(*expiry_days).clone()}
                            oninput={on_days}
                            disabled={!is_relative} />
                        <span class="hint-inline">{"(negative = already expired)"}</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="expiry_mode"
                            checked={!is_relative}
                            onclick={on_use_absolute} />
                        <span>{"Absolute date:"}</span>
                        <input type="date" class="input-date"
                            value={(*expiry_date).clone()}
                            onchange={on_date}
                            disabled={is_relative} />
                    </label>
                </div>
            </div>

            // ── File prefix ──────────────────────────
            <div class="form-group">
                <label for="prefix">{"File Name Prefix"}</label>
                <input id="prefix" type="text" class="input-field"
                    value={pfx.clone()}
                    oninput={on_prefix}
                    placeholder="mycert" />
                <p class="hint mono">
                    {format!("{pfx}_certificate.pem  ·  {pfx}_privatekey.pem")}
                </p>
            </div>

            // ── CA inputs (CA-signed mode only) ──────
            if mode == CertMode::CaSigned {
                <div class="ca-section">
                    <div class="ca-section-title">{"CA Credentials"}</div>
                    <div class="form-group">
                        <label>{"CA Certificate (PEM)"}</label>
                        <div class="file-pick-row">
                            <label class="btn-file" for="ca-cert-file">{"Choose file"}</label>
                            <input type="file" id="ca-cert-file" class="file-hidden"
                                accept=".pem,.crt,.cer,.cert"
                                onchange={on_ca_cert_file} />
                            if !(*ca_cert_name).is_empty() {
                                <span class="file-chosen">{"✓ "}{(*ca_cert_name).clone()}</span>
                                <button class="btn-remove" onclick={clear_ca_cert}>{"✕"}</button>
                            }
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{"CA Private Key (PEM)"}</label>
                        <div class="file-pick-row">
                            <label class="btn-file" for="ca-key-file">{"Choose file"}</label>
                            <input type="file" id="ca-key-file" class="file-hidden"
                                accept=".pem,.key"
                                onchange={on_ca_key_file} />
                            if !(*ca_key_name).is_empty() {
                                <span class="file-chosen">{"✓ "}{(*ca_key_name).clone()}</span>
                                <button class="btn-remove" onclick={clear_ca_key}>{"✕"}</button>
                            }
                        </div>
                    </div>
                </div>
            }

            <button class="btn-primary" onclick={on_generate}>
                {btn_label}
            </button>

            if let Some(e) = &*error {
                <div class="alert-error">{e}</div>
            }

            if has_result {
                <div class="result-box">
                    <p class="result-title">{"Ready — click to download"}</p>
                    <button class="btn-download" onclick={on_download_cert}>
                        {format!("⬇  {pfx}_certificate.pem  ({cert_label})")}
                    </button>
                    <button class="btn-download" onclick={on_download_key}>
                        {format!("⬇  {pfx}_privatekey.pem")}
                    </button>
                    if has_chain {
                        <button class="btn-download btn-download-chain" onclick={on_download_chain}>
                            {format!("⬇  {pfx}_chain.pem  (leaf + CA)")}
                        </button>
                    }
                </div>
            }
        </div>
    }
}

fn main() {
    yew::Renderer::<App>::new().render();
}
