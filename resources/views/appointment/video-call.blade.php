@extends('layouts.layout')

@section('title', 'Video Consultation')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

<style>

:root {
    --primary: #1a6fc4;
    --primary-dk: #145aa0;
    --bg-dark: #0f0f0f;
    --bg-card: #1a1a1a;
    --bg-light: #2a2a2a;
    --text-primary: #ffffff;
    --text-secondary: #e0e0e0;
    --border-color: #404040;
    --success: #198754;
    --danger: #dc3545;
    --warning: #ffc107;
}

/* Light mode (override for light theme) */
body.light-theme {
    --bg-dark: #ffffff;
    --bg-card: #f5f5f5;
    --bg-light: #e8e8e8;
    --text-primary: #202124;
    --text-secondary: #5f6368;
    --border-color: #dadce0;
}

* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
    background: var(--bg-dark);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* ===== FULLSCREEN CALL CONTAINER ===== */
.video-call-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-dark);
    z-index: 1000;
}

/* ===== TOP BAR ===== */
.call-header {
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
    height: 56px;
    min-height: 56px;
    flex-shrink: 0;
}

.call-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.call-header-title .title-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.call-header-title .title-main {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.call-header-title .title-meta {
    font-size: 0.8rem;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.call-header-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.call-timer {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.5px;
}

.call-timer i {
    font-size: 1rem;
    animation: pulse-timer 2s infinite;
}

@keyframes pulse-timer {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.call-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    background: rgba(25, 135, 84, 0.2);
    border-radius: 6px;
    font-size: 0.8rem;
    color: var(--success);
    font-weight: 600;
}

.call-status.ended {
    background: rgba(108, 117, 125, 0.2);
    color: #adb5bd;
}

/* ===== MAIN VIDEO AREA ===== */
.call-content {
    flex: 1;
    position: relative;
    overflow: hidden;
    background: #000000;
}

.video-main {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000000;
}

.video-main video,
.video-main div[id] {
    width: 100% !important;
    height: 100% !important;
    object-fit: fill !important;
    object-position: center center !important;
    max-width: none !important;
    max-height: none !important;
}

.video-self-container video,
.video-self-container div[id] {
    width: 100% !important;
    height: 100% !important;
    object-position: center center !important;
    max-width: none !important;
    max-height: none !important;
    position: absolute;
    top: 0;
    left: 0;
}

.video-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 16px;
    color: #808080;
    background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
    z-index: 1;
}

.video-placeholder i {
    font-size: 4rem;
    opacity: 0.6;
}

.video-placeholder span {
    font-size: 1rem;
    opacity: 0.7;
}

/* ===== FLOATING SELF VIDEO ===== */
.video-self-container {
    position: absolute;
    bottom: 30px;
    right: 16px;
    width: 260px;
    height: 195px;
    border-radius: 12px;
    overflow: hidden;
    background: #000000;
    border: 2px solid var(--border-color);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    z-index: 100;
    transition: all 0.3s ease;
}

.video-self-container:hover {
    transform: scale(1.03);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.video-self-label {
    position: absolute;
    bottom: 8px;
    left: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 4px;
    z-index: 10;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100px;
}

/* ===== BOTTOM CONTROL BAR ===== */
.call-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    padding: 12px 16px;
    border-radius: 12px;
    z-index: 200;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.control-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: #3d3d3d;
    color: white;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.control-btn:hover {
    background: #4d4d4d;
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.control-btn.active {
    background: #27c752;
}

.control-btn.active:hover {
    background: #1fb842;
}

.control-btn.off,
.control-btn.muted {
    background: #da3633;
}

.control-btn.off:hover,
.control-btn.muted:hover {
    background: #d23d3a;
}

.control-btn.end-call {
    background: #ea4335;
    width: 56px;
    height: 56px;
    font-size: 1.4rem;
}

.control-btn.end-call:hover {
    background: #d33827;
}

.control-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* Tooltip */
.control-btn[title]::after {
    content: attr(title);
    position: absolute;
    bottom: -32px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.control-btn:hover[title]::after {
    opacity: 1;
}

/* ===== LIVE SUBTITLES & FLOATING OVERLAY ===== */
.subtitle-overlay-box {
    position: absolute;
    bottom: 90px;
    left: 50%;
    transform: translateX(-50%);
    max-width: 82%;
    min-width: 260px;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    padding: 10px 18px;
    color: #ffffff;
    font-size: 1.05rem;
    line-height: 1.5;
    text-align: center;
    z-index: 1050;
    pointer-events: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease, transform 0.2s ease;
}

.subtitle-overlay-box[dir="rtl"] {
    text-align: right;
    font-family: "Noto Naskh Arabic", "Jameel Noori Nastaleeq", "Segoe UI", Tahoma, sans-serif;
    font-size: 1.15rem;
}

.subtitle-speaker-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--primary);
    color: white;
    margin-bottom: 4px;
    margin-right: 6px;
}

.subtitle-overlay-box[dir="rtl"] .subtitle-speaker-badge {
    margin-right: 0;
    margin-left: 6px;
}

.subtitle-lang-wrapper {
    display: inline-flex;
    align-items: center;
}

.subtitle-lang-dropdown {
    background: #3d3d3d;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    transition: all 0.2s ease;
}

.subtitle-lang-dropdown:hover {
    background: #4d4d4d;
}

.subtitle-lang-dropdown option {
    background: #1a1a1a;
    color: white;
}

.control-btn.cc-active {
    background: #1a6fc4;
}

.control-btn.cc-starting {
    background: #ffc107;
    color: #000;
}

/* ===== PRESCRIPTION PANEL (Doctor Only - Sidebar) ===== */
.rx-sidebar {
    position: fixed;
    right: -480px;
    top: 0;
    width: 480px;
    max-width: 100vw;
    height: 100vh;
    background: var(--bg-card);
    border-left: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1200;
    box-shadow: -4px 0 24px rgba(0, 0, 0, 0.4);
    box-sizing: border-box;
}

.rx-sidebar.open {
    right: 0;
}

.rx-toggle-btn {
    position: fixed;
    bottom: 20px;
    right: 16px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background: var(--primary);
    color: white;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 1190;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.rx-toggle-btn:hover {
    background: var(--primary-dk);
    transform: scale(1.1);
}

.rx-sidebar.open ~ .rx-toggle-btn {
    opacity: 0;
    pointer-events: none;
}

.rx-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dk) 100%);
    color: white;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
    min-height: 56px;
    box-sizing: border-box;
}

.rx-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rx-header .close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}

.rx-header .close-btn:hover {
    transform: scale(1.2);
}

.rx-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    box-sizing: border-box;
}

.rx-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
    box-sizing: border-box;
}

.rx-field > label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 2px;
}

.rx-field textarea {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.88rem;
    color: var(--text-primary);
    resize: vertical;
    min-height: 70px;
    transition: all 0.2s;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
}

.rx-field textarea::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

.rx-field textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--bg-dark);
    box-shadow: 0 0 0 3px rgba(26, 111, 196, 0.2);
}

.med-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
}

.med-row {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
    box-sizing: border-box;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.med-row:hover {
    border-color: rgba(26, 111, 196, 0.4);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.med-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: -2px;
}

.med-row-num {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--primary);
    background: rgba(26, 111, 196, 0.15);
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.med-field-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box;
}

.med-field-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.3px;
    margin-bottom: 0;
}

.med-sub-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 10px;
    width: 100%;
    min-width: 0 !important;
    box-sizing: border-box;
}

.med-sub-grid > * {
    min-width: 0 !important;
    max-width: 100% !important;
}

.med-row input[type="text"],
.rx-field input[type="text"] {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 0.84rem;
    color: #1e293b;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
    resize: none !important;
    height: 38px;
    transition: border-color 0.2s, background 0.2s;
}

.med-row input[type="text"]:focus,
.rx-field input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    background: #ffffff;
    box-shadow: 0 0 0 2px rgba(26, 111, 196, 0.2);
}

.med-row select {
    resize: none !important;
}

/* Select2 Modern Light Styling */
.rx-sidebar .select2-container {
    width: 100% !important;
    display: block !important;
    box-sizing: border-box !important;
    resize: none !important;
}

.rx-sidebar .select2-container *,
.rx-sidebar .select2-selection,
.rx-sidebar .med-row * {
    resize: none !important;
}

.rx-sidebar ::-webkit-resizer,
.rx-sidebar .select2-container ::-webkit-resizer,
.rx-sidebar .med-row ::-webkit-resizer {
    display: none !important;
}

.rx-sidebar .select2-container--default .select2-selection--single {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    height: 38px !important;
    display: flex !important;
    align-items: center !important;
    box-sizing: border-box !important;
    transition: border-color 0.2s, background-color 0.2s;
    overflow: hidden !important;
    resize: none !important;
}

.rx-sidebar .select2-container--default.select2-container--focus .select2-selection--single,
.rx-sidebar .select2-container--default.select2-container--open .select2-selection--single {
    border-color: var(--primary) !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(26, 111, 196, 0.2) !important;
}

.rx-sidebar .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #1e293b !important;
    font-size: 0.84rem !important;
    line-height: 36px !important;
    padding-left: 10px !important;
    padding-right: 28px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.rx-sidebar .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #94a3b8 !important;
    opacity: 1 !important;
}

.rx-sidebar .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
    right: 8px !important;
}

.rx-sidebar .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #64748b transparent transparent transparent !important;
    border-width: 5px 4px 0 4px !important;
}

.rx-sidebar .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
    border-color: transparent transparent #64748b transparent !important;
    border-width: 0 4px 5px 4px !important;
}

.rx-sidebar .select2-container--default .select2-selection--single .select2-selection__clear {
    color: var(--danger) !important;
    font-size: 1.1rem !important;
    margin-right: 14px !important;
    line-height: 36px !important;
}

/* Select2 Multiple (Dose Timing) */
.rx-sidebar .select2-container--default .select2-selection--multiple {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    min-height: 38px !important;
    box-sizing: border-box !important;
    padding: 3px 6px !important;
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 4px !important;
    resize: none !important;
    transition: border-color 0.2s, background-color 0.2s;
}

.rx-sidebar .select2-container--default.select2-container--focus .select2-selection--multiple,
.rx-sidebar .select2-container--default.select2-container--open .select2-selection--multiple {
    border-color: var(--primary) !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(26, 111, 196, 0.2) !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-selection__rendered {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 4px !important;
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: var(--primary) !important;
    border: 1px solid var(--primary-dk) !important;
    color: #ffffff !important;
    border-radius: 4px !important;
    padding: 3px 8px 3px 6px !important;
    font-size: 0.8rem !important;
    font-weight: 500 !important;
    margin: 2px 2px !important;
    display: inline-flex !important;
    flex-direction: row-reverse !important;
    align-items: center !important;
    gap: 6px !important;
    line-height: 1.4 !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    background: transparent !important;
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    color: rgba(255, 255, 255, 0.85) !important;
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    padding: 0 !important;
    margin: 0 !important;
    position: static !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    height: auto !important;
    transition: color 0.15s;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ffffff !important;
    background: transparent !important;
    background-color: transparent !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
    color: #ffffff !important;
    font-size: 0.8rem !important;
    padding: 0 !important;
    margin: 0 !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-search--inline {
    margin: 0 !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
}

.rx-sidebar .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
    color: #1e293b !important;
    font-size: 0.82rem !important;
    margin: 0 !important;
    padding: 2px 4px !important;
    height: 28px !important;
    min-width: 80px !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.select2-dropdown {
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    z-index: 10000 !important;
}

.select2-container--default .select2-search--dropdown {
    padding: 8px !important;
    background-color: #ffffff !important;
    border-bottom: 1px solid #f1f5f9 !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    color: #1e293b !important;
    font-size: 0.84rem !important;
    padding: 7px 10px !important;
    outline: none !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: var(--primary) !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(26, 111, 196, 0.15) !important;
}

.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-size: 0.84rem !important;
    color: #334155 !important;
    border-bottom: 1px solid #f8fafc !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected],
.select2-container--default .select2-results__option--highlighted {
    background-color: var(--primary) !important;
    color: #ffffff !important;
}

.select2-container--default .select2-results__option[aria-selected="true"] {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
    font-weight: 600 !important;
}

.select2-container--default .select2-results__message {
    color: #64748b !important;
    font-size: 0.82rem !important;
}

.btn-add-med {
    background: rgba(26, 111, 196, 0.08);
    border: 1.5px dashed var(--primary);
    color: var(--primary);
    border-radius: 6px;
    padding: 9px;
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    transition: all 0.2s;
    margin-top: 4px;
}

.btn-add-med:hover {
    background: rgba(26, 111, 196, 0.18);
    border-color: var(--primary-dk);
}

.btn-del-med {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 3px 6px;
    border-radius: 4px;
    transition: transform 0.2s, background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-del-med:hover {
    transform: scale(1.15);
    background: rgba(239, 68, 68, 0.15);
}

.rx-footer {
    border-top: 1px solid var(--border-color);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.btn-save-rx {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
    white-space: nowrap;
}

.btn-save-rx:hover:not(:disabled) {
    background: var(--primary-dk);
}

.btn-save-rx:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.save-status {
    font-size: 0.75rem;
    color: var(--text-secondary);
    white-space: nowrap;
}

.save-status.ok {
    color: var(--success);
}

.save-status.err {
    color: var(--danger);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .call-header-title .title-meta {
        display: none;
    }

    .video-self-container {
        width: 140px;
        height: 105px;
        bottom: 90px;
        right: 12px;
    }

    .call-controls {
        bottom: 16px;
        padding: 10px 12px;
        gap: 8px;
    }

    .control-btn {
        width: 44px;
        height: 44px;
        font-size: 1rem;
    }

    .control-btn.end-call {
        width: 52px;
        height: 52px;
    }

    .rx-sidebar {
        width: 100%;
        right: -100%;
    }
}

/* ===== THEME AGNOSTIC FIXES ===== */
@media (prefers-color-scheme: light) {
    body:not(.light-theme) {
        --bg-dark: #ffffff;
        --bg-card: #f5f5f5;
        --bg-light: #e8e8e8;
        --text-primary: #202124;
        --text-secondary: #5f6368;
        --border-color: #dadce0;
    }
}
</style>
@endpush

@section('content')
<div class="video-call-container">
    <!-- Top Bar -->
    <div class="call-header">
        <div class="call-header-title">
            <div class="title-text">
                <div class="title-main">
                    @if (auth()->user()->role === 'doctor')
                        Patient. {{ $appointment->patient->user->name }}
                    @else
                        Dr. {{ $appointment->doctor->user->name }}
                    @endif
                </div>

                
                <div class="title-meta">
                    {{ $appointment->appointment_date->format('M d') }} · 
                    {{ $appointment->formatted_time }}
                </div>
            </div>
        </div>

        <div class="call-header-right">
            <div class="call-timer">
                <i class="ti ti-clock"></i>
                <span id="callTimer">00:00</span>
            </div>
            <div class="call-status" id="callStatus">
                <span id="statusDot">●</span>
                <span id="statusText">Live</span>
            </div>
        </div>
    </div>

    <!-- Main Video Area -->
    <div class="call-content">
        <!-- Remote User Video (Main) -->
        <div class="video-main" id="remote-video">
            <div class="video-placeholder" id="remotePlaceholder">
                <i class="ti ti-user-circle"></i>
                <span>Waiting for {{ $callData['is_doctor'] ? 'Patient' : 'Doctor' }}…</span>
            </div>
        </div>

        <!-- Self Video (Floating) -->
        <div class="video-self-container" id="local-video">
            <div class="video-placeholder" id="localPlaceholder">
                <i class="ti ti-user-circle"></i>
            </div>
            <div class="video-self-label">
                {{ $callData['is_doctor'] ? 'You' : 'You' }}
            </div>
        </div>

        <!-- Floating Subtitle Overlay -->
        <div class="subtitle-overlay-box" id="subtitleOverlay" style="display: none;" dir="ltr">
            <span class="subtitle-speaker-badge" id="subtitleSpeaker">Speaker</span>
            <span class="subtitle-text" id="subtitleText"></span>
        </div>

        <!-- Control Bar (Bottom Center) -->
        <div class="call-controls">
            <button type="button" class="control-btn active" id="btnMic" title="Mute/Unmute">
                <i class="ti ti-microphone" id="micIcon"></i>
            </button>
            <button type="button" class="control-btn active" id="btnCam" title="Camera On/Off">
                <i class="ti ti-video" id="camIcon"></i>
            </button>
            
            <!-- Live Subtitles (CC) Button -->
            <button type="button" class="control-btn off" id="btnCC" title="Live Subtitles (CC)">
                <span id="ccIcon" style="font-weight: 800; font-size: 0.88rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.5px;">CC</span>
            </button>

            <button type="button" class="control-btn end-call" id="btnEnd" title="End Call">
                <i class="ti ti-phone-off"></i>
            </button>
        </div>
    </div>
</div>

<!-- Prescription Sidebar (Doctor Only) -->
@if($callData['is_doctor'])
<div class="rx-sidebar" id="rxSidebar">
    <div class="rx-header">
        <h5>
            <i class="ti ti-prescription"></i>
            Prescription
        </h5>
        <button class="close-btn" onclick="document.getElementById('rxSidebar').classList.remove('open')">
            <i class="ti ti-x"></i>
        </button>
    </div>

    <form id="rxForm" class="rx-body" autocomplete="off">
        <div class="rx-field">
            <label>Diagnosis</label>
            <textarea id="rxDiagnosis" rows="3"
                placeholder="Enter diagnosis…">{{ $appointment->prescription->diagnosis ?? '' }}</textarea>
        </div>

        <div class="rx-field">
            <label>Medicines</label>
            <div class="med-list" id="medList">
                {{-- Medicine rows dynamically generated with Select2 --}}
            </div>
            <button type="button" class="btn-add-med" id="btnAddMed">
                <i class="ti ti-plus"></i> Add Medicine
            </button>
        </div>

        <div class="rx-field">
            <label>Additional Notes</label>
            <textarea id="rxNotes" rows="3"
                placeholder="Additional notes…">{{ $appointment->prescription->notes ?? '' }}</textarea>
        </div>
    </form>

    <div class="rx-footer">
        <button class="btn-save-rx" id="btnSaveRx">
            <i class="ti ti-device-floppy"></i> Save
        </button>
        <span class="save-status" id="saveStatus"></span>
    </div>
</div>

<!-- Prescription Toggle Button (Doctor Only) -->
<button class="rx-toggle-btn" id="rxToggleBtn" title="Prescription">
    <i class="ti ti-prescription"></i>
</button>
@endif

@if (auth()->user()->role === 'patient')
{{-- Rating Modal Component --}}
@include('components.rating-modal')
@endif

@endsection

@push('scripts')
{{-- External Libraries --}}
<script src="https://download.agora.io/sdk/release/AgoraRTC_N-4.22.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
/* ================================================================
   CONFIG (injected from PHP)
   ================================================================ */
const AGORA_APP_ID    = @json($callData['app_id']);
const AGORA_CHANNEL   = @json($callData['channel_name']);
const AGORA_TOKEN     = @json($callData['token']);
const AGORA_UID       = @json($callData['uid']);
const IS_DOCTOR       = @json($callData['is_doctor']);
const CURRENT_USER_ROLE = @json((string) auth()->user()->role);
const APPOINTMENT_STATUS = @json((string) $appointment->status);
const APPOINTMENT_PATIENT_ID = @json((int) $appointment->patient_id);
const CURRENT_PATIENT_ID = @json((int) optional(auth()->user()->patient)->id);
const DOCTOR_AVATAR_URL = @json(!empty(optional($appointment->doctor->user)->profile_image) ? optional($appointment->doctor->user)->profile_image_url : null);
const EXPIRES_AT      = @json($callData['expires_at']);   // unix ts
const APPT_ID         = @json($appointment->id);
const CSRF_TOKEN      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const END_CALL_URL    = "{{ route('appointments.end-call', ['id' => $appointment->id]) }}";
const RX_STORE_URL    = "{{ route('appointments.prescription.store', ['id' => $appointment->id]) }}";
const RX_FETCH_URL    = "{{ route('appointments.prescription.show', ['id' => $appointment->id]) }}";
const MED_SEARCH_URL  = "{{ route('medicines.search') }}";
const MED_CREATE_URL  = "{{ route('medicines.quick-create') }}";
const APPT_SHOW_URL   = "{{ route('appointments.show', $appointment) }}";
const APPT_RATING_URL = "{{ route('appointments.rating.show', ['id' => $appointment->id]) }}";

// Translation Endpoints
const TRANSLATION_START_URL = "{{ route('appointments.translation.start', ['id' => $appointment->id]) }}";
const TRANSLATION_STOP_URL  = "{{ route('appointments.translation.stop', ['id' => $appointment->id]) }}";
const TRANSLATION_LANG_URL  = "{{ route('appointments.translation.language', ['id' => $appointment->id]) }}";
const TRANSLATION_STATUS_URL= "{{ route('appointments.translation.status', ['id' => $appointment->id]) }}";

const APPOINTMENT_SUBTITLE_LANGUAGE  = @json($callData['subtitle_language'] ?? config('subtitles.default', 'ur-PK'));
const APPOINTMENT_SUBTITLE_DIRECTION = @json($callData['subtitle_direction'] ?? 'ltr');
const APPOINTMENT_SUBTITLE_LANG_NAME = @json($callData['subtitle_lang_name'] ?? 'Subtitles');
const CC_STORAGE_KEY = `agora_cc_enabled_${APPT_ID}`;

let ccActive = false;
let ccStarting = false;
let currentSubtitleLang = IS_DOCTOR ? 'en-US' : APPOINTMENT_SUBTITLE_LANGUAGE;
let subtitleClearTimer = null;

// ========== FIXED START TIMESTAMP TIMER ==========
const CALL_STARTED_AT_TS   = @json($callData['call_started_at_ts']);
const SESSION_DURATION_MS  = 30 * 60 * 1000; // 30 minutes
const CALL_START_MS        = CALL_STARTED_AT_TS * 1000;
const CALL_END_MS          = CALL_START_MS + SESSION_DURATION_MS;

let callDurationTimer = null;
let disconnectTimer = null;
let disconnectStartTime = null;
let callEnded = false;
let handlingCallEnd = false;

/* ================================================================
   TOASTR CONFIGURATION
   ================================================================ */
toastr.options = {
    positionClass: 'toast-top-right',
    timeOut: 4000,
    extendedTimeOut: 2000,
    progressBar: true,
    closeButton: true,
};

/* ================================================================
   TIMER (displays synchronized elapsed time & manages 30-min auto-end)
   ================================================================ */
const timerEl = document.getElementById('callTimer');

function updateTimer() {
    const now = Date.now();
    const elapsedMs = Math.max(0, now - CALL_START_MS);
    const m = String(Math.floor(elapsedMs / 60000)).padStart(2, '0');
    const s = String(Math.floor((elapsedMs % 60000) / 1000)).padStart(2, '0');
    timerEl.textContent = m + ':' + s;

    if (now >= CALL_END_MS && !callEnded) {
        callEnded = true;
        handleCallDurationExpired();
    }
}

function startCallTimer() {
    if (Date.now() >= CALL_END_MS) {
        callEnded = true;
        handleCallDurationExpired();
        return;
    }

    callDurationTimer = setInterval(updateTimer, 1000);
    updateTimer(); // Update immediately
}

function stopCallTimer() {
    if (callDurationTimer) {
        clearInterval(callDurationTimer);
        callDurationTimer = null;
    }
    if (disconnectTimer) {
        clearInterval(disconnectTimer);
        disconnectTimer = null;
    }
}

function handleCallDurationExpired() {
    stopCallTimer();
    
    Swal.fire({
        title: 'Consultation Completed',
        text: 'The maximum 30-minute session duration has been reached.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        confirmButtonText: 'View Summary',
    }).then(async () => {
        let fmtDuration = '';
        if (IS_DOCTOR) {
            const endRes = await fetch(END_CALL_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
            }).catch(() => {});
            if (endRes && endRes.ok) {
                const endData = await endRes.json();
                fmtDuration = endData.formatted_duration || '';
            }
        }
        await leaveCall();
        await handleCallEnded(fmtDuration);
    });
}

/* ================================================================
   AGORA RTC & DISCONNECT TRACKING
   ================================================================ */
const client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
let localTracks = { audio: null, video: null };
let micMuted = false;
let camOff   = false;

const DISCONNECT_STORAGE_KEY = `agora_disc_ts_${APPT_ID}`;
const JOINED_STORAGE_KEY     = `agora_joined_${APPT_ID}`;

function updateDisconnectStatus(isDisconnected) {
    const remoteBox = document.getElementById('remote-video');
    if (!remoteBox) return;

    if (isDisconnected) {
        let storedTs = sessionStorage.getItem(DISCONNECT_STORAGE_KEY);
        if (!storedTs) {
            storedTs = String(Date.now());
            sessionStorage.setItem(DISCONNECT_STORAGE_KEY, storedTs);
        }
        disconnectStartTime = parseInt(storedTs, 10) || Date.now();

        const messageText = IS_DOCTOR 
            ? 'Patient disconnected. Waiting for patient to rejoin…' 
            : 'Doctor disconnected. Waiting for doctor to reconnect…';

        const discSecs = Math.max(0, Math.floor((Date.now() - disconnectStartTime) / 1000));
        const dm = String(Math.floor(discSecs / 60)).padStart(2, '0');
        const ds = String(discSecs % 60).padStart(2, '0');

        remoteBox.innerHTML = `
            <div class="video-placeholder">
                <i class="ti ti-plug-off text-warning mb-2" style="font-size: 3.5rem;"></i>
                <span class="fw-bold text-white fs-5 mb-1">${messageText}</span>
                <small class="text-warning font-monospace" id="disconnectTimerText">(Disconnected ${dm}:${ds})</small>
            </div>`;

        if (!disconnectTimer) {
            disconnectTimer = setInterval(() => {
                const discEl = document.getElementById('disconnectTimerText');
                if (discEl && disconnectStartTime) {
                    const secs = Math.max(0, Math.floor((Date.now() - disconnectStartTime) / 1000));
                    const m = String(Math.floor(secs / 60)).padStart(2, '0');
                    const s = String(secs % 60).padStart(2, '0');
                    discEl.textContent = `(Disconnected ${m}:${s})`;
                }
            }, 1000);
        }
    } else {
        sessionStorage.removeItem(DISCONNECT_STORAGE_KEY);
        if (disconnectTimer) {
            clearInterval(disconnectTimer);
            disconnectTimer = null;
        }
        disconnectStartTime = null;
    }
}

const TRANSCRIBER_UID     = 999999;
const TRANSCRIBER_PUB_UID = 999998;
const DOCTOR_UID          = @json((int) optional(optional($appointment->doctor)->user)->id);
const EXPECTED_REMOTE_UID = IS_DOCTOR 
    ? @json((int) optional(optional($appointment->patient)->user)->id)
    : DOCTOR_UID;

console.log('=== RTC AUDIO DEBUG ===');
console.log('AGORA SDK VERSION:', typeof AgoraRTC !== 'undefined' ? AgoraRTC.VERSION : 'Unknown');
console.log('LOCAL_UID:', AGORA_UID, 'typeof:', typeof AGORA_UID);
console.log('EXPECTED_REMOTE_UID:', EXPECTED_REMOTE_UID, 'typeof:', typeof EXPECTED_REMOTE_UID);
console.log('ROLE:', IS_DOCTOR ? 'Doctor' : 'Patient');
console.log('CHANNEL:', AGORA_CHANNEL);
console.log('SECURE_CONTEXT:', window.isSecureContext);

function isHumanRemoteParticipant(uid) {
    const numUid = Number(uid);
    if (numUid === TRANSCRIBER_UID || numUid === TRANSCRIBER_PUB_UID || numUid === Number(AGORA_UID)) {
        return false;
    }
    if (EXPECTED_REMOTE_UID > 0) {
        return numUid === Number(EXPECTED_REMOTE_UID);
    }
    return true; // Safe fallback: treat any non-bot, non-self participant as the remote human
}

if (typeof AgoraRTC.onAutoplayFailed === 'function') {
    AgoraRTC.onAutoplayFailed(() => {
        console.error('[AGORA AUTOPLAY FAILED]');
        toastr.info('Click anywhere on the screen to enable remote audio playback.', 'Audio Policy Alert', { timeOut: 8000 });
    });
}

// Global click fallback to play audio if browser restricted initial autoplay
document.addEventListener('click', () => {
    for (const user of client.remoteUsers) {
        if (user.audioTrack && isHumanRemoteParticipant(user.uid)) {
            if (!user.audioTrack.isPlaying) {
                user.audioTrack.play().catch(e => console.warn('Audio play retry failed:', e));
            }
        }
    }
}, { once: false });

function updateCameraOffStatus(isCamOff) {
    const remoteBox = document.getElementById('remote-video');
    if (!remoteBox) return;

    if (isCamOff) {
        const messageText = IS_DOCTOR 
            ? 'Patient turned camera off' 
            : 'Doctor turned camera off';

        remoteBox.innerHTML = `
            <div class="video-placeholder">
                <i class="ti ti-video-off text-secondary mb-2" style="font-size: 3.5rem;"></i>
                <span class="fw-bold text-white fs-5 mb-1">${messageText}</span>
            </div>`;
    }
}

async function subscribeToRemoteUser(user, mediaType) {
    if (!isHumanRemoteParticipant(user.uid)) {
        return;
    }

    const typeTag = mediaType ? mediaType.toUpperCase() : 'MEDIA';
    console.log(`[ATTEMPTING ${typeTag} SUBSCRIBE] ${user.uid}`);
    try {
        await client.subscribe(user, mediaType);
        console.log(`[${typeTag} SUBSCRIBE SUCCESS] ${user.uid}`);

        if (mediaType === 'video' && user.videoTrack) {
            updateDisconnectStatus(false);
            const remoteBox = document.getElementById('remote-video');
            remoteBox?.querySelector('.video-placeholder')?.remove();
            user.videoTrack.play('remote-video');
        }

        if (mediaType === 'audio') {
            const trackExists = !!user.audioTrack;
            console.log(`[REMOTE AUDIO TRACK EXISTS] ${trackExists ? 'YES' : 'NO'}`);
            if (user.audioTrack) {
                if (typeof user.audioTrack.setVolume === 'function') {
                    try {
                        user.audioTrack.setVolume(100);
                        console.log(`[REMOTE AUDIO VOLUME SET] 100 for UID: ${user.uid}`);
                    } catch (volErr) {
                        console.warn('Set volume error:', volErr);
                    }
                }
                console.log(`[CALLING REMOTE AUDIO PLAY] UID: ${user.uid}`);
                try {
                    await user.audioTrack.play();
                    console.log(`[REMOTE AUDIO PLAY CALLED] UID: ${user.uid}`);
                    
                    let remoteSampleCount = 0;
                    const remoteInterval = setInterval(() => {
                        if (user.audioTrack && remoteSampleCount < 15) {
                            const level = typeof user.audioTrack.getVolumeLevel === 'function' ? user.audioTrack.getVolumeLevel() : 0;
                            console.log(`[REMOTE AUDIO LEVEL] UID ${user.uid}: ${level.toFixed(4)}`);
                            remoteSampleCount++;
                        } else {
                            clearInterval(remoteInterval);
                        }
                    }, 1000);
                } catch (playErr) {
                    console.error(`[REMOTE AUDIO PLAY FAILED] UID: ${user.uid}`, playErr);
                }
            }
        }
    } catch (error) {
        console.error(`[${typeTag} SUBSCRIBE FAILED]`, {
            UID: user.uid,
            mediaType,
            errorCode: error?.code,
            errorMessage: error?.message || String(error)
        });
    }
}

function registerAgoraEvents() {
    client.on('connection-state-change', (curState, prevState, reason) => {
        console.log(`[RTC CONNECTION] previous: ${prevState}, current: ${curState}, reason: ${reason}`);
    });

    client.on('user-published', async (user, mediaType) => {
        const isHuman = isHumanRemoteParticipant(user.uid);
        console.log('[USER PUBLISHED]', {
            UID: user.uid,
            typeof_UID: typeof user.uid,
            mediaType: mediaType,
            EXPECTED_REMOTE: EXPECTED_REMOTE_UID,
            typeof_EXPECTED: typeof EXPECTED_REMOTE_UID,
            IS_HUMAN_REMOTE: isHuman
        });

        if (!isHuman) {
            return;
        }

        sessionStorage.setItem(JOINED_STORAGE_KEY, 'true');
        updateDisconnectStatus(false);
        await subscribeToRemoteUser(user, mediaType);
    });

    client.on('user-joined', (user) => {
        console.log(`[Agora User Joined] UID: ${user.uid}`);
        if (Number(user.uid) === TRANSCRIBER_UID || Number(user.uid) === TRANSCRIBER_PUB_UID) {
            console.log('[AGORA STT BOT ONLINE] Bot joined channel — listening for microphone audio!');
            toastr.info('STT Transcriber connected. Speak into mic now!');
        }
    });

    client.on('user-unpublished', (user, mediaType) => {
        console.log(`[Agora User Unpublished] UID: ${user.uid}, mediaType: ${mediaType}`);
        if (!isHumanRemoteParticipant(user.uid)) {
            return;
        }

        if (mediaType === 'video') {
            updateCameraOffStatus(true);
        }
    });

    client.on('user-left', (user) => {
        console.log(`[Agora User Left] UID: ${user?.uid}`);
        if (user && !isHumanRemoteParticipant(user.uid)) {
            return;
        }

        sessionStorage.setItem(JOINED_STORAGE_KEY, 'true');
        updateDisconnectStatus(true);
    });

    console.log('[STT STREAM LISTENER REGISTERED]', {
        timestamp: new Date().toISOString(),
        channel: AGORA_CHANNEL,
        localUid: AGORA_UID
    });

    client.on('stream-message', (uid, stream) => {
        console.log('[RAW AGORA STREAM MESSAGE]', {
            senderUid: String(uid),
            senderType: typeof uid,
            payloadType: stream?.constructor?.name || typeof stream,
            byteLength: stream?.byteLength ?? stream?.length ?? null,
            receivedAt: new Date().toISOString()
        });
        handleTranscriptionStreamMessage(uid, stream);
    });
}

async function syncRemoteParticipantState() {
    let humanUser = null;
    for (const user of client.remoteUsers) {
        if (isHumanRemoteParticipant(user.uid)) {
            humanUser = user;
            break;
        }
    }

    const remoteBox = document.getElementById('remote-video');
    if (!remoteBox) return;

    if (!humanUser) {
        const hadJoined = sessionStorage.getItem(JOINED_STORAGE_KEY) === 'true';
        const hasDisconnectTs = !!sessionStorage.getItem(DISCONNECT_STORAGE_KEY);

        if (hadJoined || hasDisconnectTs) {
            updateDisconnectStatus(true);
        } else {
            const waitingText = IS_DOCTOR 
                ? 'Waiting for patient to join…' 
                : 'Waiting for doctor to join…';

            remoteBox.innerHTML = `
                <div class="video-placeholder" id="remotePlaceholder">
                    <i class="ti ti-user-circle"></i>
                    <span>${waitingText}</span>
                </div>`;
        }
        return;
    }

    sessionStorage.setItem(JOINED_STORAGE_KEY, 'true');
    sessionStorage.removeItem(DISCONNECT_STORAGE_KEY);

    console.log('[SYNC REMOTE PARTICIPANT]', {
        uid: humanUser.uid,
        hasVideo: humanUser.hasVideo,
        hasAudio: humanUser.hasAudio
    });

    if (humanUser.hasVideo) {
        updateDisconnectStatus(false);
        await subscribeToRemoteUser(humanUser, 'video');
    } else {
        updateCameraOffStatus(true);
    }

    if (humanUser.hasAudio) {
        await subscribeToRemoteUser(humanUser, 'audio');
    }
}

async function initAgora() {
    if (!AGORA_APP_ID) {
        console.error('❌ FATAL: Agora App ID is missing or empty!');
        toastr.error('Agora App ID is not configured. Please check your .env file.');
        return;
    }

    if (!AGORA_TOKEN) {
        console.error('❌ FATAL: Agora Token is missing or empty!');
        toastr.error('Agora authentication token is missing.');
        return;
    }

    try {
        registerAgoraEvents();
        await client.join(AGORA_APP_ID, AGORA_CHANNEL, AGORA_TOKEN, AGORA_UID);
        
        startCallTimer();
        toastr.success('Connected to video consultation');

        await syncRemoteParticipantState();

        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            toastr.warning('Microphone and camera access requires a secure HTTPS connection.', 'Security Warning', { timeOut: 8000 });
        }

        // Initialize audio track independently
        try {
            localTracks.audio = await AgoraRTC.createMicrophoneAudioTrack();
            console.log('[MIC TRACK CREATED] YES');
            console.log('[MIC MUTED]', localTracks.audio.muted);
            console.log('[MIC ENABLED]', localTracks.audio.enabled);

            let micSampleCount = 0;
            const micInterval = setInterval(() => {
                if (localTracks.audio && micSampleCount < 15) {
                    const level = typeof localTracks.audio.getVolumeLevel === 'function' ? localTracks.audio.getVolumeLevel() : 0;
                    console.log(`[LOCAL MIC AUDIO LEVEL] ${level.toFixed(4)}`);
                    micSampleCount++;
                } else {
                    clearInterval(micInterval);
                }
            }, 1000);
        } catch (audioErr) {
            console.log('[MIC TRACK CREATED] NO - Error:', audioErr);
            handleMediaError(audioErr, 'Microphone');
        }

        // Initialize video track independently
        try {
            localTracks.video = await AgoraRTC.createCameraVideoTrack();
            const localBox = document.getElementById('local-video');
            localBox?.querySelector('.video-placeholder')?.remove();
            localTracks.video.play('local-video');
        } catch (videoErr) {
            console.warn('Camera initialization failed:', videoErr);
            handleMediaError(videoErr, 'Camera');
        }

        // Publish any successfully initialized tracks
        const tracksToPublish = [];
        if (localTracks.audio) tracksToPublish.push(localTracks.audio);
        if (localTracks.video) tracksToPublish.push(localTracks.video);

        console.log('[PUBLISHING TRACKS]', {
            audio: !!localTracks.audio,
            video: !!localTracks.video,
            count: tracksToPublish.length
        });

        if (tracksToPublish.length > 0) {
            try {
                await client.publish(tracksToPublish);
                if (localTracks.audio) console.log('[AUDIO PUBLISH SUCCESS]');
                if (localTracks.video) console.log('[VIDEO PUBLISH SUCCESS]');
            } catch (pubErr) {
                console.error('[PUBLISH FAILED]', pubErr);
            }
        }

        startStatusPolling();
        
        const storedCc = sessionStorage.getItem(CC_STORAGE_KEY);
        let initialCc = false;
        if (storedCc === 'true') {
            initialCc = true;
        } else {
            initialCc = false;
        }

        ccActive = initialCc;
        sessionStorage.setItem(CC_STORAGE_KEY, ccActive ? 'true' : 'false');

        const btn = document.getElementById('btnCC');
        const icon = document.getElementById('ccIcon');
        if (btn) btn.className = ccActive ? 'control-btn cc-active' : 'control-btn off';
        if (icon) icon.textContent = 'CC';

        console.log('[CC INITIAL STATE]', {
            role: IS_DOCTOR ? 'doctor' : 'patient',
            ccActive: ccActive,
            storageValue: storedCc,
            buttonShowsEnabled: btn?.classList.contains('cc-active'),
            ariaPressed: btn?.getAttribute('aria-pressed')
        });

        // Always auto-start Agora STT backend service for Doctor role so Patient can receive Urdu subtitles regardless of Doctor CC view state
        if (CURRENT_USER_ROLE === 'doctor') {
            startTranslation(false).catch(err => {
                console.warn('[AUTO SUBTITLES START NOTICE]', err);
            });
        } else if (!IS_DOCTOR) {
            if (shouldPatientUrduAsrRun()) {
                console.log('[REJOIN CC RESTORED] Starting Patient Urdu ASR...');
                startPatientUrduAsr();
            }
        }
        
    } catch (error) {
        console.error('❌ Agora initialization failed:', error);
        const errorMsg = error?.message || String(error);
        toastr.error('Video call connection failed: ' + errorMsg);
        throw error;
    }
}

function handleMediaError(err, deviceType) {
    const errName = err?.name || err?.code || '';
    const msg = err?.message || String(err);

    if (errName === 'NotAllowedError' || msg.includes('PERMISSION_DENIED') || msg.includes('Permission denied')) {
        toastr.error(`${deviceType} permission was denied by browser. Please allow permission in the address bar.`, 'Permission Denied');
    } else if (errName === 'NotReadableError' || errName === 'TrackStartError' || msg.includes('occupy') || msg.includes('busy') || msg.includes('Device in use') || msg.includes('NOT_READABLE')) {
        toastr.warning(`${deviceType} is in use by another application or browser tab. Close other apps using your camera and click the camera button to enable.`, 'Device In Use', { timeOut: 9000 });
    } else if (errName === 'NotFoundError' || msg.includes('DEVICE_NOT_FOUND')) {
        toastr.warning(`No ${deviceType.toLowerCase()} device detected on this system.`, 'No Device Found');
    } else {
        toastr.warning(`Could not start ${deviceType.toLowerCase()}: ${msg}`, `${deviceType} Unavailable`);
    }
}

initAgora().catch(err => {
    console.error('❌ Agora init error:', err);
    toastr.error('Could not connect to video call: ' + err.message);
});

/* ================================================================
   CONTROL BUTTONS
   ================================================================ */

// Mute / unmute mic (supports lazy creation if track was missing)
document.getElementById('btnMic').addEventListener('click', async () => {
    try {
        if (!localTracks.audio) {
            toastr.info('Initializing microphone…');
            localTracks.audio = await AgoraRTC.createMicrophoneAudioTrack();
            await client.publish([localTracks.audio]);
            micMuted = false;
            const btn  = document.getElementById('btnMic');
            const icon = document.getElementById('micIcon');
            btn.classList.remove('muted');
            icon.className = 'ti ti-microphone';
            toastr.success('Microphone activated');
            return;
        }

        micMuted = !micMuted;
        await localTracks.audio.setMuted(micMuted);
        const btn  = document.getElementById('btnMic');
        const icon = document.getElementById('micIcon');
        btn.classList.toggle('muted', micMuted);
        icon.className = micMuted ? 'ti ti-microphone-off' : 'ti ti-microphone';

        console.log('[PATIENT MIC STATE]', { enabled: !micMuted });

        if (!IS_DOCTOR) {
            if (micMuted) {
                console.log('[PATIENT URDU ASR STOPPED]', { reason: 'microphone-muted' });
                stopPatientUrduAsr();
            } else if (shouldPatientUrduAsrRun()) {
                startPatientUrduAsr();
            }
        }
    } catch (err) {
        handleMediaError(err, 'Microphone');
    }
});

// Toggle camera (supports lazy creation if track was missing)
document.getElementById('btnCam').addEventListener('click', async () => {
    try {
        if (!localTracks.video) {
            toastr.info('Initializing camera…');
            localTracks.video = await AgoraRTC.createCameraVideoTrack();
            const localBox = document.getElementById('local-video');
            localBox?.querySelector('.video-placeholder')?.remove();
            localTracks.video.play('local-video');
            await client.publish([localTracks.video]);
            camOff = false;
            const btn  = document.getElementById('btnCam');
            const icon = document.getElementById('camIcon');
            btn.classList.remove('off');
            icon.className = 'ti ti-video';
            toastr.success('Camera activated');
            return;
        }

        camOff = !camOff;
        await localTracks.video.setMuted(camOff);
        const btn  = document.getElementById('btnCam');
        const icon = document.getElementById('camIcon');
        btn.classList.toggle('off', camOff);
        icon.className = camOff ? 'ti ti-video-off' : 'ti ti-video';
    } catch (err) {
        handleMediaError(err, 'Camera');
    }
});

// End call button
document.getElementById('btnEnd')?.addEventListener('click', async () => {
    const confirmTitle = IS_DOCTOR ? 'End Consultation?' : 'Leave Call?';
    const confirmText  = IS_DOCTOR 
        ? 'Are you sure you want to end this consultation? This action will mark the appointment as completed.' 
        : 'Are you sure you want to leave this call?';

    Swal.fire({
        title: confirmTitle,
        text: confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: IS_DOCTOR ? 'Yes, End Consultation' : 'Yes, Leave',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        
        try {
            let durationFmt = '';
            if (IS_DOCTOR) {
                const res = await fetch(END_CALL_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    durationFmt = data.formatted_duration || '';
                }
            }
            await leaveCall();
            await handleCallEnded(durationFmt);
        } catch (err) {
            toastr.error('Error ending call: ' + err.message);
        }
    });
});

async function leaveCall() {
    if (ccActive) {
        await stopTranslation().catch(() => {});
    }
    stopCallTimer();
    localTracks.audio?.close();
    localTracks.video?.close();
    await client.leave();
}

/* ================================================================
   SERVER-SIDE SESSION POLLING
   ================================================================ */
const CALL_STATUS_URL = "{{ route('appointments.call-status', ['id' => $appointment->id]) }}";
let statusPollInterval = null;

async function pollCallStatus() {
    if (callEnded) return;
    
    try {
        const res = await fetch(CALL_STATUS_URL, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        if (!res.ok) return;
        const data = await res.json();

        if (!data.active && !callEnded) {
            callEnded = true;
            clearInterval(statusPollInterval);
            toastr.warning('Call session has ended.');
            await leaveCall().catch(() => {});
            await handleCallEnded(data.formatted_duration || '');
        }
    } catch (err) {
        console.warn('Call status poll failed:', err.message);
    }
}

function startStatusPolling() {
    statusPollInterval = setInterval(pollCallStatus, 10000);
}

/* ================================================================
   PRESCRIPTION — DOCTOR SIDE (LIVE SEARCHABLE & CREATABLE CONTROLS)
   ================================================================ */
if (IS_DOCTOR) {
    $(function() {
        const rxSidebar = document.getElementById('rxSidebar');
        const rxToggleBtn = document.getElementById('rxToggleBtn');

        rxToggleBtn?.addEventListener('click', () => {
            rxSidebar?.classList.toggle('open');
        });

        const DEFAULT_DOSAGES = [
            '5 mg', '10 mg', '15 mg', '20 mg', '25 mg', '50 mg', 
            '100 mg', '125 mg', '200 mg', '250 mg', '500 mg', '750 mg', '1000 mg',
            '5 ml', '10 ml', '15 ml'
        ];

        const DEFAULT_INTAKES = [
            'With water', 'With milk', 'With food', 'After food', 'Before food',
            'On empty stomach', 'Oral', 'Tablet', 'Capsule', 'Syrup',
            'Injection', 'Intramuscular injection', 'Intravenous injection',
            'Subcutaneous injection', 'Inhalation', 'Nebulization',
            'Topical application', 'Drops', 'Nasal', 'Sublingual', 'As directed'
        ];

        const DEFAULT_DOSE_TIMINGS = [
            'Morning',
            'Afternoon',
            'Evening',
            'Night',
            'Bedtime',
            'Before Breakfast',
            'After Breakfast',
            'Before Lunch',
            'After Lunch',
            'Before Dinner',
            'After Dinner',
            'Before Food',
            'After Food',
            'Empty Stomach',
            'As Needed'
        ];

        const INITIAL_MEDICINES = @json($appointment->prescription->medicines ?? []);

        function updateRowNumbers() {
            document.querySelectorAll('#medList .med-row').forEach((row, idx) => {
                const badge = row.querySelector('.med-row-num');
                if (badge) badge.textContent = `#${idx + 1}`;
            });
        }

        function initRowSelects(row, initialData = {}) {
            const $row = $(row);
            const $medSelect = $row.find('.rx-med-select');
            const $dosageSelect = $row.find('.rx-dosage-select');
            const $intakeSelect = $row.find('.rx-intake-select');
            const $timingSelect = $row.find('.rx-timing-select');

            if (typeof $.fn.select2 === 'function') {
                // 1. Medicine Searchable + Creatable Select (Queries medicines table ONLY)
                $medSelect.select2({
                    placeholder: 'Search medicine...',
                    allowClear: true,
                    minimumInputLength: 2,
                    dropdownParent: $('#rxSidebar'),
                    ajax: {
                        url: MED_SEARCH_URL,
                        dataType: 'json',
                        delay: 300,
                        data: function(params) {
                            return { q: params.term };
                        },
                        processResults: function(data, params) {
                            const results = (data && data.results) ? [...data.results] : [];
                            const term = $.trim(params.term || '');
                            
                            if (term.length >= 2) {
                                const exactMatch = results.some(item => 
                                    item.text.toLowerCase() === term.toLowerCase()
                                );
                                if (!exactMatch) {
                                    results.unshift({
                                        id: '__NEW__' + term,
                                        text: 'Add "' + term + '"',
                                        isNew: true,
                                        newTerm: term
                                    });
                                }
                            }
                            return { results: results };
                        },
                        cache: true
                    },
                    language: {
                        noResults: function() {
                            return "No medicine found. Type to add custom.";
                        },
                        searching: function() {
                            return "Searching medicines…";
                        },
                        inputTooShort: function(args) {
                            return "Type 2 or more letters to search…";
                        },
                        errorLoading: function() {
                            return "No results or search timeout.";
                        }
                    },
                    templateResult: function(data) {
                        if (!data.id) return data.text;
                        if (data.isNew) {
                            return $(`<span><i class="ti ti-plus text-primary me-1"></i> <strong>Add "${esc(data.newTerm)}"</strong></span>`);
                        }
                        return data.text;
                    },
                    templateSelection: function(data) {
                        if (data.isNew) {
                            return data.newTerm || data.text;
                        }
                        return data.text;
                    }
                });

                // Quick creation if doctor selects the Add option
                $medSelect.on('select2:select', async function(e) {
                    const selected = e.params.data;
                    if (selected && (selected.isNew || String(selected.id).startsWith('__NEW__'))) {
                        const rawName = selected.newTerm || selected.text.replace(/^Add\s+"|"$/g, '');
                        try {
                            const res = await fetch(MED_CREATE_URL, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF_TOKEN,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ name: rawName })
                            });
                            const resData = await res.json();
                            if (res.ok && resData.text) {
                                const option = new Option(resData.text, resData.text, true, true);
                                $medSelect.empty().append(option).trigger('change');
                                toastr.success(resData.message || `Medicine "${resData.text}" selected.`);
                            } else {
                                throw new Error(resData.message || resData.error || 'Failed to add medicine');
                            }
                        } catch (err) {
                            toastr.error(err.message || 'Error creating medicine');
                            $medSelect.val(null).trigger('change');
                        }
                    }
                });

                // 2. Dosage Searchable + Creatable Select
                DEFAULT_DOSAGES.forEach(dose => {
                    $dosageSelect.append(new Option(dose, dose, false, false));
                });
                if (initialData.dosage) {
                    if (!DEFAULT_DOSAGES.includes(initialData.dosage)) {
                        $dosageSelect.append(new Option(initialData.dosage, initialData.dosage, true, true));
                    } else {
                        $dosageSelect.val(initialData.dosage);
                    }
                }

                $dosageSelect.select2({
                    placeholder: 'Select or enter dosage...',
                    allowClear: true,
                    tags: true,
                    dropdownParent: $('#rxSidebar'),
                    createTag: function(params) {
                        const term = $.trim(params.term);
                        if (term === '') return null;
                        return {
                            id: term,
                            text: term,
                            newTag: true
                        };
                    }
                });

                // 3. Intake / Administration Method Searchable + Creatable Select
                DEFAULT_INTAKES.forEach(intake => {
                    $intakeSelect.append(new Option(intake, intake, false, false));
                });
                const currentIntake = initialData.intake || initialData.instructions || '';
                if (currentIntake) {
                    if (!DEFAULT_INTAKES.includes(currentIntake)) {
                        $intakeSelect.append(new Option(currentIntake, currentIntake, true, true));
                    } else {
                        $intakeSelect.val(currentIntake);
                    }
                }

                $intakeSelect.select2({
                    placeholder: 'Select or enter intake...',
                    allowClear: true,
                    tags: true,
                    dropdownParent: $('#rxSidebar'),
                    createTag: function(params) {
                        const term = $.trim(params.term);
                        if (term === '') return null;
                        return {
                            id: term,
                            text: term,
                            newTag: true
                        };
                    }
                });

                // 4. Dose Timing Searchable + Creatable Multi-Select
                DEFAULT_DOSE_TIMINGS.forEach(timing => {
                    $timingSelect.append(new Option(timing, timing, false, false));
                });

                const rawTiming = initialData.duration || '';
                let selectedTimings = [];
                if (rawTiming) {
                    if (Array.isArray(rawTiming)) {
                        selectedTimings = rawTiming;
                    } else {
                        selectedTimings = rawTiming.split(',').map(s => s.trim()).filter(Boolean);
                    }
                }

                selectedTimings.forEach(t => {
                    if (!$timingSelect.find(`option[value="${esc(t)}"]`).length) {
                        $timingSelect.append(new Option(t, t, true, true));
                    }
                });

                $timingSelect.val(selectedTimings);

                $timingSelect.select2({
                    placeholder: 'Select or enter dose timing...',
                    allowClear: true,
                    tags: true,
                    dropdownParent: $('#rxSidebar'),
                    createTag: function(params) {
                        const term = $.trim(params.term);
                        if (term === '') return null;
                        return {
                            id: term,
                            text: term,
                            newTag: true
                        };
                    }
                });
            }
        }

        function addMedRow(data = {}) {
            const medList = document.getElementById('medList');
            if (!medList) return;

            const rowIndex = medList.querySelectorAll('.med-row').length + 1;
            const row = document.createElement('div');
            row.className = 'med-row';
            
            const initialName = data.name ?? '';
            const initialDosage = data.dosage ?? '';
            const initialIntake = data.intake ?? data.instructions ?? '';
            const initialDuration = data.duration ?? '';

            row.innerHTML = `
                <div class="med-row-header">
                    <span class="med-row-num">#${rowIndex}</span>
                    <button type="button" class="btn-del-med" title="Remove Medicine">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
                
                <div class="med-field-block">
                    <label class="med-field-label">Medicine</label>
                    <select class="rx-med-select" data-field="name" style="width: 100%;">
                        ${initialName ? `<option value="${esc(initialName)}" selected>${esc(initialName)}</option>` : '<option value=""></option>'}
                    </select>
                </div>
                
                <div class="med-sub-grid">
                    <div class="med-field-block">
                        <label class="med-field-label">Dosage</label>
                        <select class="rx-dosage-select" data-field="dosage" style="width: 100%;">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="med-field-block">
                        <label class="med-field-label">Intake Method</label>
                        <select class="rx-intake-select" data-field="intake" style="width: 100%;">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <div class="med-field-block">
                    <label class="med-field-label">Dose Timing</label>
                    <select class="rx-timing-select" data-field="duration" multiple="multiple" style="width: 100%;">
                    </select>
                </div>
            `;

            row.querySelector('.btn-del-med').addEventListener('click', () => {
                $(row).find('select').each(function() {
                    if ($(this).data('select2')) {
                        $(this).select2('destroy');
                    }
                });
                row.remove();
                updateRowNumbers();
            });

            medList.appendChild(row);

            initRowSelects(row, {
                name: initialName,
                dosage: initialDosage,
                intake: initialIntake,
                duration: initialDuration
            });
        }

        document.getElementById('btnAddMed')?.addEventListener('click', () => addMedRow());

        // Populate initial rows from database or start with 1 empty row
        if (Array.isArray(INITIAL_MEDICINES) && INITIAL_MEDICINES.length > 0) {
            INITIAL_MEDICINES.forEach(med => addMedRow(med));
        } else {
            addMedRow();
        }

        document.getElementById('btnSaveRx')?.addEventListener('click', saveRx);

        async function saveRx() {
            const btn    = document.getElementById('btnSaveRx');
            const status = document.getElementById('saveStatus');
            btn.disabled = true;
            status.textContent = 'Saving…';
            status.className   = 'save-status';

            const medicines = [];
            document.querySelectorAll('#medList .med-row').forEach(row => {
                const nameEl = row.querySelector('[data-field="name"]');
                const dosageEl = row.querySelector('[data-field="dosage"]');
                const intakeEl = row.querySelector('[data-field="intake"]');
                const $timingSelect = $(row).find('.rx-timing-select');

                const name = nameEl ? nameEl.value.trim() : '';
                if (!name || name.startsWith('__NEW__')) return;

                let timingVal = $timingSelect.val();
                let timingStr = '';
                if (Array.isArray(timingVal)) {
                    timingStr = timingVal.join(', ');
                } else if (timingVal) {
                    timingStr = String(timingVal).trim();
                }

                medicines.push({
                    name: name,
                    dosage:   dosageEl ? dosageEl.value.trim() : '',
                    intake:   intakeEl ? intakeEl.value.trim() : '',
                    duration: timingStr,
                });
            });

            try {
                const res = await fetch(RX_STORE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        diagnosis: document.getElementById('rxDiagnosis').value,
                        medicines: medicines,
                        notes:     document.getElementById('rxNotes').value,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    if (data.errors) {
                        const msgs = Object.values(data.errors).flat().join(', ');
                        throw new Error(msgs);
                    }
                    throw new Error(data.message ?? data.error ?? 'Save failed');
                }
                status.textContent = '✓ Saved';
                status.className   = 'save-status ok';
                toastr.success('Prescription saved successfully');
            } catch (err) {
                status.textContent = '✗ ' + err.message;
                status.className   = 'save-status err';
                toastr.error('Prescription save failed: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        }
    });
}

/* ================================================================
   UTILITIES & CALL END HANDLING
   ================================================================ */
function esc(str) {
    return (str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function handleCallEnded(durationFmt = '') {
    if (handlingCallEnd) {
        return;
    }

    handlingCallEnd = true;
    stopCallTimer();

    const callStatus = document.getElementById('callStatus');
    const statusText = document.getElementById('statusText');
    const statusDot  = document.getElementById('statusDot');

    if (callStatus) callStatus.classList.add('ended');
    if (statusText) statusText.textContent = 'Ended';
    if (statusDot)  statusDot.textContent = '●';

    document.getElementById('btnMic').disabled = true;
    document.getElementById('btnCam').disabled = true;
    document.getElementById('btnEnd')?.setAttribute('disabled', 'disabled');

    if (CURRENT_USER_ROLE === 'doctor' || CURRENT_USER_ROLE === 'admin') {
        setTimeout(() => {
            window.location.href = APPT_SHOW_URL;
        }, 1000);
        return;
    }

    // Patient side: Check for review and show summary + rating modal
    try {
        const response = await fetch(APPT_RATING_URL, {
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            }
        });

        const data = response.ok ? await response.json() : {};
        const appointmentStatus = String(data.appointment_status ?? '');
        const appointmentPatientId = parseInt(data.appointment_patient_id ?? APPOINTMENT_PATIENT_ID, 10);
        const currentPatientId = parseInt(data.current_patient_id ?? CURRENT_PATIENT_ID, 10);
        const currentUserRole = String(data.current_user_role ?? CURRENT_USER_ROLE);
        const alreadyReviewed = Boolean(data.has_rating);

        const canReview =
            currentUserRole === 'patient'
            && parseInt(appointmentPatientId, 10) === parseInt(currentPatientId, 10)
            && (appointmentStatus === 'completed' || callEnded)
            && !alreadyReviewed;

        if (canReview) {
            const summaryText = durationFmt ? `Total Consultation Duration: ${durationFmt}` : 'Your consultation has ended.';
            await Swal.fire({
                title: 'Consultation Completed',
                text: summaryText,
                icon: 'success',
                confirmButtonText: 'Rate Doctor',
            });

            if (typeof window.openRatingModal === 'function') {
                const doctorName = document.querySelector('.title-main')?.textContent?.trim() || 'Your Doctor';
                window.openRatingModal(doctorName, DOCTOR_AVATAR_URL);
                return;
            }
        }
    } catch (error) {
        console.warn('Could not check for existing rating:', error);
    }

    setTimeout(() => {
        window.location.href = APPT_SHOW_URL;
    }, 1000);
}

/* ================================================================
   LIVE SUBTITLES & REAL-TIME TRANSLATION JS
   ================================================================ */
const VIEWER_SUBTITLE_LANGUAGE = IS_DOCTOR ? 'en-US' : APPOINTMENT_SUBTITLE_LANGUAGE;

/**
 * Agora Speech-to-Text Protobuf Message Decoder
 * Decodes binary Protobuf payloads (Agora.SpeechToText.Text) sent over RTC stream-message
 */
const IGNORED_STT_TOKENS = new Set([
    'translate', 'transcribe', 'transcribez', 'translation', 'translations', 
    'text', 'texts', 'stream', 'speech', 'agora', 'vendor', 'version', 
    'seqnum', 'lang', 'language', 'words', 'word', 'is_final', 'isfinal', 
    'flag', 'time', 'uid', 'user_id', 'speaker_uid'
]);

function decodeAgoraSttProtobuf(bytes) {
    if (!bytes) return null;
    let u8Array = null;

    if (bytes instanceof Uint8Array) {
        u8Array = bytes;
    } else if (bytes instanceof ArrayBuffer) {
        u8Array = new Uint8Array(bytes);
    } else if (bytes && bytes.buffer instanceof ArrayBuffer) {
        u8Array = new Uint8Array(bytes.buffer, bytes.byteOffset || 0, bytes.byteLength || bytes.buffer.byteLength);
    } else {
        return null;
    }

    let pos = 0;
    const len = u8Array.length;

    function readVarint() {
        let result = 0;
        let shift = 0;
        while (pos < len) {
            const b = u8Array[pos++];
            result |= (b & 0x7F) << shift;
            if ((b & 0x80) === 0) return result;
            shift += 7;
            if (shift >= 35) {
                while (pos < len && (u8Array[pos++] & 0x80) !== 0) {}
                return result;
            }
        }
        return result;
    }

    function readString(length) {
        if (length <= 0 || pos + length > len) {
            pos = Math.min(pos + Math.max(0, length), len);
            return '';
        }
        const strBytes = u8Array.subarray(pos, pos + length);
        pos += length;
        return new TextDecoder('utf-8').decode(strBytes);
    }

    function skipField(wireType) {
        if (wireType === 0) {
            readVarint();
        } else if (wireType === 1) {
            pos += 8;
        } else if (wireType === 2) {
            const l = readVarint();
            pos += l;
        } else if (wireType === 5) {
            pos += 4;
        } else {
            pos = len;
        }
    }

    function parseWord(wordEnd) {
        let text = '';
        let isFinal = false;
        while (pos < wordEnd && pos < len) {
            const tag = readVarint();
            const fieldNum = tag >> 3;
            const wireType = tag & 0x07;
            if (fieldNum === 1 && wireType === 2) {
                text = readString(readVarint());
            } else if (fieldNum === 4 && wireType === 0) {
                isFinal = readVarint() !== 0;
            } else {
                skipField(wireType);
            }
        }
        return { text, isFinal };
    }

    function parseTranslation(transEnd) {
        let isFinal = false;
        let lang = '';
        const texts = [];
        while (pos < transEnd && pos < len) {
            const tag = readVarint();
            const fieldNum = tag >> 3;
            const wireType = tag & 0x07;
            if (fieldNum === 1 && wireType === 0) {
                isFinal = readVarint() !== 0;
            } else if (fieldNum === 2 && wireType === 2) {
                lang = readString(readVarint());
            } else if (fieldNum === 3 && wireType === 2) {
                const str = readString(readVarint());
                if (str && str.trim() && !IGNORED_STT_TOKENS.has(str.trim().toLowerCase())) {
                    texts.push(str.trim());
                }
            } else {
                skipField(wireType);
            }
        }
        return { isFinal, lang, texts };
    }

    const result = {
        vendor: 0,
        version: 0,
        seqnum: 0,
        uid: 0,
        flag: 0,
        time: 0,
        lang: '',
        words: [],
        trans: []
    };

    try {
        while (pos < len) {
            const tag = readVarint();
            const fieldNum = tag >> 3;
            const wireType = tag & 0x07;

            if (fieldNum === 1 && wireType === 0) {
                result.vendor = readVarint();
            } else if (fieldNum === 2 && wireType === 0) {
                result.version = readVarint();
            } else if (fieldNum === 3 && wireType === 0) {
                result.seqnum = readVarint();
            } else if (fieldNum === 4 && wireType === 0) {
                result.uid = readVarint();
            } else if (fieldNum === 5 && wireType === 0) {
                result.flag = readVarint();
            } else if (fieldNum === 6 && wireType === 0) {
                result.time = readVarint();
            } else if (fieldNum === 7 && wireType === 2) {
                result.lang = readString(readVarint());
            } else if (fieldNum === 10 && wireType === 2) {
                const wLen = readVarint();
                const wordEnd = pos + wLen;
                const w = parseWord(wordEnd);
                if (w && w.text) {
                    result.words.push(w);
                }
                pos = wordEnd;
            } else if (fieldNum === 11 && wireType === 2) {
                const tLen = readVarint();
                const transEnd = pos + tLen;
                const t = parseTranslation(transEnd);
                if (t && (t.texts.length > 0 || t.text || t.lang)) {
                    result.trans.push(t);
                }
                pos = transEnd;
            } else {
                skipField(wireType);
            }
        }

        if (result.words.length > 0 && !result.text) {
            result.text = result.words.map(w => w.text || w.word || '').filter(Boolean).join(' ').trim();
        }
    } catch (e) {
        console.warn('[AGORA STT PROTOBUF PARSE WARN]', e);
    }

    return result;
}

function cleanSubtitleText(rawStr) {
    if (!rawStr || typeof rawStr !== 'string') return '';
    let cleaned = rawStr.replace(/[\x00-\x1F\x7F-\x9F\uFFFD\uFEFF]/g, '');
    return cleaned.replace(/\s+/g, ' ').trim();
}

function handleTranscriptionStreamMessage(uid, stream) {
    let rawByteLength = null;
    if (stream instanceof Uint8Array || stream instanceof ArrayBuffer) {
        rawByteLength = stream.byteLength;
    } else if (stream && stream.buffer) {
        rawByteLength = stream.buffer.byteLength;
    } else if (stream && stream.data) {
        rawByteLength = stream.data.byteLength || stream.data.length || null;
    } else if (typeof stream === 'string') {
        rawByteLength = stream.length;
    }

    console.log('[DOCTOR STREAM MESSAGE RECEIVED]', {
        senderUid: String(uid),
        senderType: typeof uid,
        payloadType: stream?.constructor?.name || typeof stream,
        byteLength: rawByteLength,
        role: CURRENT_USER_ROLE,
        timestamp: new Date().toISOString()
    });

    console.log('[STT STAGE 1 RAW]', {
        senderUid: String(uid),
        streamType: typeof stream,
        ccActive: ccActive,
        receivedAt: new Date().toISOString()
    });

    try {
        let textData = null;
        let rawBuffer = null;

        if (typeof stream === 'string') {
            textData = stream;
        } else if (stream instanceof Uint8Array || stream instanceof ArrayBuffer) {
            rawBuffer = stream;
        } else if (stream && stream.buffer) {
            rawBuffer = stream.buffer;
        } else if (stream && stream.data) {
            if (typeof stream.data === 'string') {
                textData = stream.data;
            } else {
                rawBuffer = stream.data;
            }
        }

        if (!textData && rawBuffer) {
            try {
                const decStr = new TextDecoder('utf-8').decode(rawBuffer);
                if (decStr && decStr.trim().startsWith('{') && decStr.trim().endsWith('}')) {
                    textData = decStr.trim();
                }
            } catch (e) {}
        }

        let payload = null;

        // 1. If payload is JSON string, parse as JSON
        if (textData) {
            try {
                payload = JSON.parse(textData);
                console.log('[DOCTOR CUSTOM MESSAGE DECODE]', {
                    senderUid: String(uid),
                    decoded: !!payload,
                    messageType: payload?.type || 'unknown',
                    text: payload?.text || ''
                });
                if (payload && payload.type === 'patient_urdu_asr') {
                    handlePatientUrduAsrMessage(payload);
                    return;
                }
            } catch (e) {
                if (!rawBuffer) rawBuffer = new TextEncoder().encode(textData);
            }
        }

        // 2. If payload is binary (Uint8Array / Protobuf), parse using Protobuf decoder
        if (!payload && rawBuffer) {
            payload = decodeAgoraSttProtobuf(rawBuffer);
        }

        if (!payload) {
            console.warn('[STT DECODE FAILED] Payload could not be decoded as JSON or Protobuf');
            return;
        }

        console.log('[STT STAGE 5 DECODE SUCCESS]', payload);

        // Extract actual human speaker UID from decoded payload fields (Agora STT bot 999998 transcribes Doctor UID audio)
        let actualSpeakerUid = payload.uid || payload.user_id || payload.speaker_uid;
        const numSpeaker = Number(actualSpeakerUid);

        if (!actualSpeakerUid || numSpeaker === TRANSCRIBER_UID || numSpeaker === TRANSCRIBER_PUB_UID || numSpeaker === 0) {
            actualSpeakerUid = DOCTOR_UID;
        }

        console.log('[STT SPEAKER UID]', { speakerUid: actualSpeakerUid, rawPayloadUid: payload.uid });

        // Self-caption suppression: do not display self-speech captions on the speaker's own screen
        const isSelf = String(actualSpeakerUid) === String(AGORA_UID);
        console.log('[STT SELF CHECK]', {
            speakerUid: actualSpeakerUid,
            localUid: AGORA_UID,
            isSelf: isSelf,
            suppressed: isSelf
        });

        if (isSelf) {
            console.log('[STT SELF CAPTION SUPPRESSED] Hiding self-speech caption on speaker screen');
            return;
        }

        // Part 1: If Doctor is viewing, suppress Patient speech packets coming from Agora Cloud STT bot (999998).
        // Patient -> Doctor subtitles are handled authoritatively by Patient browser custom Urdu ASR (patient_urdu_asr).
        if (IS_DOCTOR && String(actualSpeakerUid) === String(EXPECTED_REMOTE_UID)) {
            console.log('[DOCTOR PATIENT CLOUD STT SUPPRESSED]', {
                speakerUid: actualSpeakerUid,
                source: 'agora-stt-bot',
                rawText: payload?.text || payload?.sentence || ''
            });
            return;
        }

        let subtitleText = '';
        let selectedLang = null;
        const targetLang = VIEWER_SUBTITLE_LANGUAGE || 'ur-PK';
        const targetBase = targetLang.split('-')[0].toLowerCase();
        const isUrduViewer = targetBase === 'ur' || targetLang.toLowerCase().includes('ur');

        const availLangs = Array.isArray(payload.trans) ? payload.trans.map(t => t.lang || t.target || '') : [];
        console.log('[STT TRANSLATIONS]', { availableLanguages: availLangs });
        console.log('[STT VIEWER LANGUAGE]', { viewerLanguage: targetLang, targetBase: targetBase });

        // 1. Look for translated text in payload.trans array or object matching target language
        if (Array.isArray(payload.trans) && payload.trans.length > 0) {
            const match = payload.trans.find(t => {
                if (!t) return false;
                const l = (t.lang || t.target || '').toLowerCase();
                return l === targetLang.toLowerCase() 
                    || l === targetBase 
                    || (isUrduViewer && (l.includes('ur') || l.includes('urdu')));
            });
            if (match) {
                selectedLang = match.lang || match.target || targetLang;
                if (Array.isArray(match.texts) && match.texts.length > 0) {
                    if (isUrduViewer) {
                        const urduTexts = match.texts.filter(t => containsUrduScript(t));
                        subtitleText = urduTexts.length > 0 ? urduTexts.join(' ').trim() : '';
                    } else {
                        subtitleText = match.texts.join(' ').trim();
                    }
                } else if (typeof match.text === 'string' && match.text) {
                    subtitleText = match.text.trim();
                } else if (Array.isArray(match.words) && match.words.length > 0) {
                    subtitleText = match.words.map(w => (typeof w === 'string' ? w : w.text || w.word || '')).filter(Boolean).join(' ').trim();
                }
            }
        }

        if (!subtitleText && payload.translations && typeof payload.translations === 'object') {
            subtitleText = payload.translations[targetLang] || payload.translations[targetBase] || '';
            if (subtitleText) selectedLang = targetLang;
        }

        if (!subtitleText && payload.translation) {
            if (typeof payload.translation === 'object') {
                subtitleText = payload.translation[targetLang] || payload.translation[targetBase] || payload.translation.text || '';
                if (subtitleText) selectedLang = targetLang;
            }
        }

        let englishSourceText = '';
        if (Array.isArray(payload.words) && payload.words.length > 0) {
            englishSourceText = payload.words.map(w => (typeof w === 'string' ? w : w.text || w.word || '')).filter(Boolean).join(' ').trim();
        } else if (payload.text && typeof payload.text === 'string') {
            englishSourceText = payload.text.trim();
        } else if (payload.sentence && typeof payload.sentence === 'string') {
            englishSourceText = payload.sentence.trim();
        }
        englishSourceText = cleanSubtitleText(englishSourceText);

        // 2. Only allow source words fallback IF viewer language is en-US (Doctor viewing English)
        if (!subtitleText && !isUrduViewer && targetBase === 'en') {
            subtitleText = englishSourceText;
        }

        if (isUrduViewer && subtitleText) {
            const urduOnlyLines = subtitleText.split(/[\r\n]+/)
                .map(l => l.trim())
                .filter(l => containsUrduScript(l));
            if (urduOnlyLines.length > 0) {
                subtitleText = urduOnlyLines.join(' ');
            } else if (!containsUrduScript(subtitleText)) {
                subtitleText = '';
                selectedLang = null;
            }
        }

        const renderingSourceFallback = !selectedLang && !!subtitleText;

        const normSource = englishSourceText.toLowerCase().replace(/\s+/g, ' ');
        const nowMs = Date.now();
        const traceId = `stt-${actualSpeakerUid}-${nowMs}`;

        console.log('[DOCTOR STT RAW FRAME]', {
            traceId: traceId,
            senderUid: String(uid),
            payloadUid: payload.uid,
            resolvedSpeakerUid: actualSpeakerUid,
            localUid: AGORA_UID,
            isSelf: isSelf,
            payloadText: payload.text || '',
            payloadTrans: payload.trans || [],
            timestamp: nowMs
        });

        console.log('[STT ROUTING DEBUG]', {
            role: CURRENT_USER_ROLE,
            localUid: AGORA_UID,
            speakerUid: actualSpeakerUid,
            viewerLanguage: targetLang,
            availableTranslations: availLangs,
            selectedLanguage: selectedLang || 'none',
            selectedText: subtitleText,
            renderingSourceFallback: renderingSourceFallback
        });

        console.log('[STT CAPTION ROUTING]', {
            role: CURRENT_USER_ROLE,
            localUid: AGORA_UID,
            speakerUid: actualSpeakerUid,
            expectedRemoteUid: EXPECTED_REMOTE_UID,
            isSelf: isSelf,
            ccActive: ccActive,
            originalText: englishSourceText,
            translatedText: subtitleText,
            willRender: ccActive && !isSelf && (!!subtitleText || isUrduViewer),
            reason: !ccActive ? 'viewer-cc-disabled' : (isSelf ? 'self-speech-suppressed' : (isUrduViewer ? 'stabilizing-for-urdu-translation' : 'ready-to-render'))
        });

        // For Urdu viewer (Patient), buffer and stabilize Doctor STT English speech before translating
        if (isUrduViewer && !selectedLang) {
            if (englishSourceText) {
                processDoctorSttFrame(actualSpeakerUid, englishSourceText, payload, traceId);
            }
            return;
        }

        if (!subtitleText || !subtitleText.trim()) {
            return;
        }

        // If Doctor/English viewer receives Hindi or Urdu text from STT stream, translate to English first
        if (targetBase === 'en' && (containsHindiScript(subtitleText) || containsUrduScript(subtitleText))) {
            translateAndDisplayEnglish(actualSpeakerUid, subtitleText);
            return;
        }

        let cleanSentence = subtitleText;
        if (cleanSentence.length > 140) {
            cleanSentence = cleanSentence.substring(0, 137) + '...';
        }

        console.log('[STT STAGE 8 RENDER CALLED]', {
            viewerRole: CURRENT_USER_ROLE,
            viewerLanguage: VIEWER_SUBTITLE_LANGUAGE,
            speakerUid: actualSpeakerUid,
            text: cleanSentence,
            language: selectedLang || VIEWER_SUBTITLE_LANGUAGE
        });

        displaySubtitle(actualSpeakerUid, cleanSentence, false, traceId);

    } catch (err) {
        console.warn('Error processing transcription stream:', err);
    }
}

const urduTranslationCache = new Map();
const urduToEnglishTranslationCache = new Map();
let latestUrduTranslationSeq = 0;
let doctorSttPendingUtterance = '';
let doctorSttLastDispatchedText = '';
let doctorSttStableTimer = null;
let doctorSttGeneration = 0;

function containsUrduScript(text) {
    return /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]/.test(text || '');
}

function containsHindiScript(text) {
    return /[\u0900-\u097F]/.test(text || '');
}

function containsLatinLetters(text) {
    return /[a-zA-Z]/.test(text || '');
}

function extractPureUrduText(rawText) {
    if (!rawText || typeof rawText !== 'string') return '';
    const segments = rawText.split(/[\r\n]+/)
        .flatMap(line => line.split(/(?<=[.!?؟])\s+/))
        .map(seg => cleanSubtitleText(seg))
        .filter(seg => containsUrduScript(seg) && !containsLatinLetters(seg));
    return segments.join(' ').trim();
}

function processDoctorSttFrame(actualSpeakerUid, englishSourceText, payload, traceId) {
    if (!englishSourceText || !englishSourceText.trim()) return;

    let incoming = cleanSubtitleText(englishSourceText);
    if (!incoming) return;

    // 1. Check if Agora STT protobuf metadata exposes a final result flag
    const isAgoraFlagFinal = (payload?.flag === 1 || payload?.flag === 2);
    const hasWordFinal = Array.isArray(payload?.words) && payload.words.length > 0 && payload.words[payload.words.length - 1]?.isFinal === true;
    const isAgoraFinal = isAgoraFlagFinal || hasWordFinal;

    // 2. Separate sentence preservation: if incoming STT prepends previous dispatched sentence, strip prefix
    const normIncoming = incoming.toLowerCase().replace(/\s+/g, ' ');
    const normLast = doctorSttLastDispatchedText.toLowerCase().replace(/\s+/g, ' ');
    if (normLast && normIncoming.startsWith(normLast)) {
        const stripped = incoming.substring(doctorSttLastDispatchedText.length).replace(/^[,.\s!?]+/, '').trim();
        if (stripped) {
            incoming = stripped;
        } else if (!isAgoraFinal) {
            return;
        }
    }

    const previousText = doctorSttPendingUtterance;
    let chosenText = incoming;

    // 3. Punctuation and completion heuristics
    const hasPunctuation = /[.!?؟]$/.test(incoming.trim());
    const wordCount = incoming.trim().split(/\s+/).length;
    let completionSignal = 'none';

    if (isAgoraFinal) {
        completionSignal = 'agora-final';
    } else if (hasPunctuation && wordCount >= 3) {
        completionSignal = 'punctuation-and-stable';
    }

    doctorSttPendingUtterance = chosenText;

    console.log('[DOCTOR STT BUFFER UPDATE]', {
        previousText: previousText,
        incomingText: incoming,
        chosenText: chosenText,
        isFinal: isAgoraFinal,
        completionSignal: completionSignal
    });

    const finalizeSentence = (completionSource) => {
        if (doctorSttStableTimer) {
            clearTimeout(doctorSttStableTimer);
            doctorSttStableTimer = null;
        }

        const sentenceToSend = cleanSubtitleText(doctorSttPendingUtterance);
        if (!sentenceToSend) return;

        const normToSend = sentenceToSend.toLowerCase().replace(/\s+/g, ' ');
        if (normToSend === normLast) {
            return;
        }

        doctorSttPendingUtterance = '';
        doctorSttLastDispatchedText = sentenceToSend;

        console.log('[DOCTOR STT SENTENCE READY]', {
            completeText: sentenceToSend,
            completionSource: completionSource
        });

        translateAndDisplayUrdu(actualSpeakerUid, sentenceToSend, traceId);
    };

    // If Agora provided an explicit final flag, finalize immediately
    if (isAgoraFinal) {
        finalizeSentence('agora-final');
        return;
    }

    // Reset silence / stability timer for incoming interim updates
    if (doctorSttStableTimer) {
        console.log('[DOCTOR STT SENTENCE TIMER RESET]', {
            previousCandidate: previousText,
            newCandidate: chosenText
        });
        clearTimeout(doctorSttStableTimer);
        doctorSttStableTimer = null;
    }

    const currentDocGen = ++doctorSttGeneration;
    // 750ms for completed punctuation (with >= 3 words) or 1300ms silence timeout
    const delayMs = (hasPunctuation && wordCount >= 3) ? 750 : 1300;

    doctorSttStableTimer = setTimeout(() => {
        doctorSttStableTimer = null;
        if (currentDocGen !== doctorSttGeneration) return;

        const completionSource = (hasPunctuation && wordCount >= 3) ? 'punctuation-and-stable' : 'silence-timeout';
        finalizeSentence(completionSource);
    }, delayMs);
}

async function translateAndDisplayUrdu(speakerUid, englishText, traceId = '') {
    if (!englishText || !englishText.trim()) return;
    const cleanEng = cleanSubtitleText(englishText);
    if (!cleanEng) return;

    console.log('[DOCTOR STT TRANSLATION START]', { completeText: cleanEng });

    const seq = ++latestUrduTranslationSeq;
    const normKey = cleanEng.toLowerCase().replace(/\s+/g, ' ');

    console.log('[URDU TRANSLATION REQUEST]', {
        traceId: traceId,
        requestId: seq,
        sourceText: cleanEng,
        startedAt: Date.now()
    });

    if (urduTranslationCache.has(normKey)) {
        const cachedValue = urduTranslationCache.get(normKey);
        console.log('[URDU CACHE LOOKUP]', {
            sourceText: cleanEng,
            normalizedKey: normKey,
            hit: true,
            cachedValue: cachedValue
        });

        console.log('[DOCTOR STT TRANSLATION COMPLETE]', { english: cleanEng, urdu: cachedValue });

        if (seq < latestUrduTranslationSeq) {
            console.log('[URDU TRANSLATION STALE REJECTED]', {
                traceId: traceId,
                requestId: seq,
                latestRequestId: latestUrduTranslationSeq,
                sourceText: cleanEng,
                translatedText: cachedValue
            });
            return;
        }

        displaySubtitle(speakerUid, cachedValue, false, traceId);
        return;
    }

    try {
        const startAt = Date.now();
        const res = await fetch(`https://api.mymemory.translated.net/get?q=${encodeURIComponent(cleanEng)}&langpair=en|ur`);
        const data = await res.json();
        const completedAt = Date.now();
        let urduText = data?.responseData?.translatedText;

        console.log('[URDU TRANSLATION RESPONSE]', {
            traceId: traceId,
            requestId: seq,
            sourceText: cleanEng,
            translatedText: urduText,
            startedAt: startAt,
            completedAt: completedAt,
            duration: completedAt - startAt
        });

        if (urduText && typeof urduText === 'string') {
            const cleanUrdu = extractPureUrduText(urduText);
            
            if (cleanUrdu && containsUrduScript(cleanUrdu) && !containsLatinLetters(cleanUrdu)) {

                urduTranslationCache.set(normKey, cleanUrdu);

                console.log('[DOCTOR STT TRANSLATION COMPLETE]', { english: cleanEng, urdu: cleanUrdu });

                if (seq < latestUrduTranslationSeq) {
                    console.log('[URDU TRANSLATION STALE REJECTED]', {
                        traceId: traceId,
                        requestId: seq,
                        latestRequestId: latestUrduTranslationSeq,
                        sourceText: cleanEng,
                        translatedText: cleanUrdu
                    });
                    return;
                }

                console.log('[STT URDU TRANSLATED SUCCESS]', { english: cleanEng, urdu: cleanUrdu });
                displaySubtitle(speakerUid, cleanUrdu, false, traceId);
            } else {
                console.log('[URDU TRANSLATION INVALID REJECTED]', {
                    sourceText: cleanEng,
                    translatedText: urduText,
                    containsUrdu: containsUrduScript(urduText),
                    containsLatin: containsLatinLetters(urduText)
                });
            }
        }
    } catch (e) {
        console.warn('[STT TRANSLATION FALLBACK ERROR]', e);
    }
}

const ROMAN_URDU_WORDS = new Set([
    'aap', 'ap', 'meri', 'mera', 'mere', 'awaz', 'awaaz', 'arahi', 'aa', 'rahi', 'raha', 'rahe',
    'hai', 'hain', 'ho', 'hun', 'kya', 'kia', 'kaise', 'kese', 'kahan', 'kyun', 'kyu', 'bolo',
    'batao', 'suno', 'theek', 'thik', 'shukriya', 'nahi', 'nahin', 'nhi', 'kar', 'karo', 'mujhe',
    'tum', 'tera', 'teri', 'tere', 'kuch', 'chahiye', 'hota', 'hoti', 'hote', 'wali', 'wala', 'wale'
]);

function isRomanUrdu(text) {
    if (!text || typeof text !== 'string') return false;
    const words = text.toLowerCase().replace(/[^a-z\s]/g, '').split(/\s+/).filter(Boolean);
    if (words.length === 0) return false;
    let romanCount = 0;
    for (const w of words) {
        if (ROMAN_URDU_WORDS.has(w)) romanCount++;
    }
    return (romanCount / words.length) >= 0.35;
}

let latestEnglishTranslationSeq = 0;

async function fetchEnglishTranslation(text) {
    const clean = cleanSubtitleText(text);
    if (!clean) return '';

    // 1. Try Google Translate gtx API (clean direct translation without Roman Urdu artifacts)
    try {
        const gUrl = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=en&dt=t&q=${encodeURIComponent(clean)}`;
        const gRes = await fetch(gUrl);
        if (gRes.ok) {
            const gData = await gRes.json();
            if (Array.isArray(gData) && Array.isArray(gData[0])) {
                const gText = gData[0].map(item => (item && item[0]) ? item[0] : '').join(' ').trim();
                const cleanG = cleanSubtitleText(gText);
                if (cleanG && !isRomanUrdu(cleanG) && !containsUrduScript(cleanG) && !containsHindiScript(cleanG)) {
                    return cleanG;
                }
            }
        }
    } catch (e) {
        console.warn('[GOOGLE TRANSLATION NOTICE]', e);
    }

    // 2. Fallback to MyMemory API with strict Roman Urdu suppression
    try {
        const hasHindi = containsHindiScript(clean);
        const langPair = hasHindi ? 'hi|en' : 'ur|en';
        const mUrl = `https://api.mymemory.translated.net/get?q=${encodeURIComponent(clean)}&langpair=${langPair}`;
        const mRes = await fetch(mUrl);
        if (mRes.ok) {
            const mData = await mRes.json();
            
            // Check machine translation matches first
            if (Array.isArray(mData?.matches)) {
                for (const m of mData.matches) {
                    const candidate = cleanSubtitleText(m.translation || '');
                    if (candidate && !isRomanUrdu(candidate) && !containsUrduScript(candidate) && !containsHindiScript(candidate)) {
                        return candidate;
                    }
                }
            }

            const rawText = mData?.responseData?.translatedText;
            const cleanM = cleanSubtitleText(rawText);
            if (cleanM && !isRomanUrdu(cleanM) && !containsUrduScript(cleanM) && !containsHindiScript(cleanM)) {
                return cleanM;
            }
        }
    } catch (e) {
        console.warn('[MYMEMORY TRANSLATION NOTICE]', e);
    }

    return '';
}

async function translateAndDisplayEnglish(speakerUid, nonEnglishText) {
    if (!nonEnglishText || !nonEnglishText.trim()) return;
    const cleanText = cleanSubtitleText(nonEnglishText);
    if (!cleanText) return;

    console.log('[DOCTOR ENGLISH TRANSLATION START]', { urdu: cleanText });

    const seq = ++latestEnglishTranslationSeq;
    const traceId = `pt-en-${speakerUid}-${Date.now()}`;

    // Detect if Patient already spoke English
    const hasUrduChars = containsUrduScript(cleanText);
    const hasHindiChars = containsHindiScript(cleanText);
    const hasEnglishChars = /[a-zA-Z]/.test(cleanText);

    if (!hasUrduChars && !hasHindiChars && hasEnglishChars && !isRomanUrdu(cleanText)) {
        console.log('[PATIENT ENGLISH DETECTED]', { input: cleanText });
        console.log('[DOCTOR ENGLISH TRANSLATION COMPLETE]', { urdu: cleanText, english: cleanText });
        if (seq < latestEnglishTranslationSeq) {
            console.log('[ENGLISH TRANSLATION STALE REJECTED]', { requestId: seq, latestRequestId: latestEnglishTranslationSeq, text: cleanText });
            return;
        }
        console.log('[DOCTOR ENGLISH SENTENCE RENDER]', { english: cleanText });
        displaySubtitle(speakerUid, cleanText, false, traceId);
        return;
    }

    if (urduToEnglishTranslationCache.has(cleanText)) {
        const cachedEng = urduToEnglishTranslationCache.get(cleanText);
        console.log('[URDU/HINDI → ENGLISH TRANSLATION CACHED]', { input: cleanText, output: cachedEng });
        console.log('[DOCTOR ENGLISH TRANSLATION COMPLETE]', { urdu: cleanText, english: cachedEng });
        if (seq < latestEnglishTranslationSeq) {
            console.log('[ENGLISH TRANSLATION STALE REJECTED]', { requestId: seq, latestRequestId: latestEnglishTranslationSeq, text: cachedEng });
            return;
        }
        console.log('[DOCTOR ENGLISH SENTENCE RENDER]', { english: cachedEng });
        displaySubtitle(speakerUid, cachedEng, false, traceId);
        return;
    }

    try {
        const cleanEng = await fetchEnglishTranslation(cleanText);
        if (cleanEng && !isRomanUrdu(cleanEng)) {
            urduToEnglishTranslationCache.set(cleanText, cleanEng);
            console.log('[URDU/HINDI → ENGLISH TRANSLATION]', { input: cleanText, output: cleanEng });
            console.log('[DOCTOR ENGLISH TRANSLATION COMPLETE]', { urdu: cleanText, english: cleanEng });
            if (seq < latestEnglishTranslationSeq) {
                console.log('[ENGLISH TRANSLATION STALE REJECTED]', { requestId: seq, latestRequestId: latestEnglishTranslationSeq, text: cleanEng });
                return;
            }
            console.log('[DOCTOR ENGLISH SENTENCE RENDER]', { english: cleanEng });
            displaySubtitle(speakerUid, cleanEng, false, traceId);
        } else {
            console.warn('[TRANSLATION SUPPRESSED - NO VALID ENGLISH]', { input: cleanText });
        }
    } catch (e) {
        console.warn('[URDU/HINDI TO ENGLISH TRANSLATION ERROR]', e);
    }
}

let patientUrduRecognition = null;
let patientUrduAsrActive = false;
let patientUrduAsrGeneration = 0;
let patientAsrStableTimer = null;
let patientAsrActiveUtteranceText = '';
let patientAsrSegmentCandidates = new Map();
let patientAsrLastSentIndex = -1;
let lastPatientAsrSentText = '';
let lastPatientAsrSentAt = 0;

function consolidateAsrHypothesis(existing, incoming, isFinal = false) {
    const existNorm = cleanSubtitleText(existing);
    const inNorm = cleanSubtitleText(incoming);

    if (!existNorm && inNorm) {
        return { candidate: inNorm, decision: 'new-segment' };
    }
    if (existNorm && !inNorm) {
        return { candidate: existNorm, decision: 'shorter-prefix-kept-existing' };
    }
    if (!existNorm && !inNorm) {
        return { candidate: '', decision: 'new-segment' };
    }
    if (existNorm === inNorm) {
        return { candidate: existNorm, decision: 'identical' };
    }

    // 1. Extension: incoming starts with existing
    if (inNorm.startsWith(existNorm)) {
        return { candidate: inNorm, decision: 'extension' };
    }

    // 2. Incoming is a shorter prefix: existing starts with incoming
    if (existNorm.startsWith(inNorm)) {
        return { candidate: existNorm, decision: 'shorter-prefix-kept-existing' };
    }

    // 3. Incoming is a shorter suffix: existing ends with incoming (e.g. "پھر نہیں چل" vs "نہیں چل", or "نہیں چلتا" vs "چلتا")
    if (existNorm.endsWith(inNorm)) {
        return { candidate: existNorm, decision: 'shorter-suffix-kept-existing' };
    }

    // 4. Incoming contains additional leading context: incoming ends with existing (e.g. "نہیں چل رہا" vs "کیوں نہیں چل رہا")
    if (inNorm.endsWith(existNorm)) {
        return { candidate: inNorm, decision: 'extension' };
    }

    // 5. Sliding window token overlap merge
    const existTokens = existNorm.split(/\s+/);
    const inTokens = inNorm.split(/\s+/);

    const maxOverlap = Math.min(existTokens.length, inTokens.length);
    for (let k = maxOverlap; k >= 1; k--) {
        const existSuffix = existTokens.slice(-k).join(' ');
        const inPrefix = inTokens.slice(0, k).join(' ');
        if (existSuffix === inPrefix) {
            const mergedTokens = existTokens.concat(inTokens.slice(k));
            const mergedText = mergedTokens.join(' ');
            return { candidate: mergedText, decision: 'overlap-merged' };
        }
    }

    // Check if incoming first token appears inside existing tokens
    const matchIdx = existTokens.lastIndexOf(inTokens[0]);
    if (matchIdx !== -1) {
        const mergedTokens = existTokens.slice(0, matchIdx).concat(inTokens);
        const mergedText = mergedTokens.join(' ');
        return { candidate: mergedText, decision: 'overlap-merged' };
    }

    // 6. Correction check (similar token count & high word overlap)
    const tokenDiff = Math.abs(existTokens.length - inTokens.length);
    if (tokenDiff <= 2) {
        let matchingTokens = 0;
        const existSet = new Set(existTokens);
        for (const t of inTokens) {
            if (existSet.has(t)) matchingTokens++;
        }
        const similarity = matchingTokens / Math.max(existTokens.length, inTokens.length);
        if (similarity >= 0.5) {
            return { candidate: inNorm, decision: 'correction-replaced' };
        }
    }

    // 7. If isFinal, trust Chrome's final decision if it is equal or longer in tokens
    if (isFinal && inTokens.length >= existTokens.length) {
        return { candidate: inNorm, decision: 'correction-replaced' };
    }

    // 8. Fallback: if incoming is strictly longer in words, use incoming; otherwise keep richer existing
    if (inTokens.length > existTokens.length || (inTokens.length === existTokens.length && inNorm.length > existNorm.length)) {
        return { candidate: inNorm, decision: 'extension' };
    }

    return { candidate: existNorm, decision: 'shorter-suffix-kept-existing' };
}

function checkUrduAsrSupport() {
    const hasSpeechRecognition = typeof window.SpeechRecognition !== 'undefined';
    const hasWebkitSpeechRecognition = typeof window.webkitSpeechRecognition !== 'undefined';
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const supported = !!SpeechRecognition;
    const constructorName = SpeechRecognition ? (SpeechRecognition.name || 'SpeechRecognition') : null;

    console.log('[MOBILE ASR ENVIRONMENT]', {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        isSecureContext: window.isSecureContext,
        hasSpeechRecognition: hasSpeechRecognition,
        hasWebkitSpeechRecognition: hasWebkitSpeechRecognition,
        browser: navigator.vendor || 'Unknown',
        language: navigator.language || 'Unknown'
    });

    console.log('[URDU ASR SUPPORT]', {
        supported: supported,
        constructor: constructorName
    });
    console.log('[PATIENT ASR INIT]', {
        supported: supported,
        role: CURRENT_USER_ROLE,
        language: 'ur-PK',
        micMuted: micMuted
    });
    return SpeechRecognition;
}

function shouldPatientUrduAsrRun() {
    const res = !IS_DOCTOR &&
                !micMuted &&
                APPOINTMENT_SUBTITLE_LANGUAGE.startsWith('ur') &&
                !!checkUrduAsrSupport();
    console.log('[PATIENT ASR SHOULD RUN]', {
        isDoctor: IS_DOCTOR,
        appointmentLanguage: APPOINTMENT_SUBTITLE_LANGUAGE,
        micMuted: micMuted,
        callActive: true,
        speechRecognitionSupported: !!checkUrduAsrSupport(),
        result: res
    });
    return res;
}

function startPatientUrduAsr() {
    console.log('[PATIENT URDU ASR START ATTEMPT]', {
        shouldRun: shouldPatientUrduAsrRun(),
        alreadyActive: patientUrduAsrActive
    });

    if (!shouldPatientUrduAsrRun()) {
        return;
    }
    if (patientUrduAsrActive) return;

    const SpeechRecognition = checkUrduAsrSupport();
    if (!SpeechRecognition) {
        console.warn('[PATIENT URDU ASR UNAVAILABLE] Web Speech API SpeechRecognition is not supported in this browser.');
        return;
    }

    const currentGeneration = ++patientUrduAsrGeneration;
    patientAsrLastSentIndex = -1;
    patientAsrActiveUtteranceText = '';
    patientAsrSegmentCandidates.clear();

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    try {
        patientUrduRecognition = new SpeechRecognition();
        patientUrduRecognition.lang = 'ur-PK';
        patientUrduRecognition.continuous = !isMobile;
        patientUrduRecognition.interimResults = true;

        patientUrduRecognition.onstart = () => {
            patientUrduAsrActive = true;
            console.log('[PATIENT ASR START]', {
                role: CURRENT_USER_ROLE,
                language: 'ur-PK',
                continuous: !isMobile,
                isMobile: isMobile,
                timestamp: new Date().toISOString()
            });
            console.log('[PATIENT URDU ASR STARTED]');
        };

        patientUrduRecognition.onresult = (event) => {
            if (micMuted || currentGeneration !== patientUrduAsrGeneration) {
                console.log('[PATIENT URDU ASR RESULT DROPPED]', { reason: 'microphone-muted' });
                return;
            }
            if (!event.results) return;

            console.log('[PATIENT ASR EVENT]', {
                resultIndex: event.resultIndex,
                resultsLength: event.results.length,
                generation: currentGeneration,
                micMuted: micMuted,
                callActive: true
            });

            const startIndex = Math.max(0, patientAsrLastSentIndex + 1);
            if (startIndex >= event.results.length && !isMobile) {
                return;
            }

            const effectiveStartIndex = isMobile ? 0 : startIndex;

            // 1. Update each segment candidate from effectiveStartIndex to event.results.length - 1
            for (let i = effectiveStartIndex; i < event.results.length; i++) {
                const res = event.results[i];
                if (!res || !res[0]) continue;
                const rawTranscript = cleanSubtitleText(res[0].transcript || '');
                const isFinal = !!res.isFinal;
                const segKey = `${currentGeneration}:${i}`;
                const existingObj = patientAsrSegmentCandidates.get(segKey);
                const previousCandidate = existingObj ? existingObj.text : '';

                const { candidate: chosenCandidate, decision } = consolidateAsrHypothesis(previousCandidate, rawTranscript, isFinal);
                patientAsrSegmentCandidates.set(segKey, { text: chosenCandidate, isFinal: isFinal });

                console.log('[PATIENT ASR SEGMENT UPDATE]', {
                    generation: currentGeneration,
                    index: i,
                    incoming: rawTranscript,
                    previousCandidate: previousCandidate,
                    chosenCandidate: chosenCandidate,
                    decision: decision
                });
            }

            // 2. Build the current UNSENT utterance from ordered unsent segment candidates
            const activeIndexes = [];
            const segmentCandidates = [];
            for (let i = effectiveStartIndex; i < event.results.length; i++) {
                const segKey = `${currentGeneration}:${i}`;
                const seg = patientAsrSegmentCandidates.get(segKey);
                if (seg && seg.text) {
                    activeIndexes.push(i);
                    segmentCandidates.push(seg.text);
                }
            }

            const combinedText = cleanSubtitleText(segmentCandidates.join(' '));
            if (!combinedText) return;

            // 3. Delta check against lastPatientAsrSentText
            let newTextOnly = combinedText;
            if (lastPatientAsrSentText && newTextOnly.startsWith(lastPatientAsrSentText)) {
                newTextOnly = cleanSubtitleText(newTextOnly.substring(lastPatientAsrSentText.length));
            }

            console.log('[PATIENT ASR DELTA]', {
                previousSentText: lastPatientAsrSentText,
                currentRecognizedText: combinedText,
                newTextOnly: newTextOnly
            });

            console.log('[PATIENT ASR ACTIVE UTTERANCE]', {
                generation: currentGeneration,
                indexes: activeIndexes,
                segmentCandidates: segmentCandidates,
                combinedText: newTextOnly || combinedText
            });

            const textToStabilize = newTextOnly || combinedText;
            if (!textToStabilize) return;

            const maxActiveIndex = activeIndexes.length > 0 ? Math.max(...activeIndexes) : patientAsrLastSentIndex;
            const allFinal = activeIndexes.length > 0 && activeIndexes.every(idx => patientAsrSegmentCandidates.get(`${currentGeneration}:${idx}`)?.isFinal);

            // 4. Stabilization Debounce (700ms on desktop, 500ms on mobile)
            const debounceDelay = isMobile ? 500 : 700;

            if (textToStabilize === patientAsrActiveUtteranceText && patientAsrStableTimer) {
                return;
            }

            if (patientAsrStableTimer) {
                clearTimeout(patientAsrStableTimer);
                patientAsrStableTimer = null;
            }

            patientAsrActiveUtteranceText = textToStabilize;

            console.log('[PATIENT ASR STABILIZATION SCHEDULED]', {
                generation: currentGeneration,
                combinedText: textToStabilize,
                delayMs: debounceDelay
            });

            patientAsrStableTimer = setTimeout(() => {
                patientAsrStableTimer = null;

                if (micMuted || currentGeneration !== patientUrduAsrGeneration) {
                    console.log('[PATIENT ASR STABILIZATION BLOCKED]', {
                        reason: micMuted ? 'microphone-muted' : 'stale-generation',
                        text: patientAsrActiveUtteranceText,
                        generation: currentGeneration
                    });
                    return;
                }
                if (!patientAsrActiveUtteranceText || !patientAsrActiveUtteranceText.trim()) return;

                const completeText = cleanSubtitleText(patientAsrActiveUtteranceText);
                patientAsrActiveUtteranceText = '';
                patientAsrLastSentIndex = isMobile ? -1 : Math.max(patientAsrLastSentIndex, maxActiveIndex);

                console.log('[PATIENT ASR UTTERANCE READY]', {
                    generation: currentGeneration,
                    completeText: completeText,
                    completionSource: allFinal ? 'is-final' : 'stabilized-interim'
                });

                sendPatientUrduAsrMessage(completeText);
            }, debounceDelay);
        };

        patientUrduRecognition.onerror = (event) => {
            console.warn('[PATIENT ASR ERROR]', {
                error: event.error,
                message: event.message || event.error
            });
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                patientUrduAsrActive = false;
            }
        };

        patientUrduRecognition.onend = () => {
            patientUrduAsrActive = false;
            const shouldRestart = shouldPatientUrduAsrRun() && currentGeneration === patientUrduAsrGeneration;
            console.log('[PATIENT ASR END]', {
                shouldRestart: shouldRestart,
                micMuted: micMuted,
                isMobile: isMobile
            });
            console.log('[PATIENT URDU ASR ENDED]');

            if (shouldRestart) {
                setTimeout(() => {
                    if (shouldPatientUrduAsrRun() && !patientUrduAsrActive && currentGeneration === patientUrduAsrGeneration) {
                        try {
                            if (isMobile) {
                                patientAsrSegmentCandidates.clear();
                                patientAsrLastSentIndex = -1;
                            }
                            patientUrduRecognition?.start();
                        } catch (e) {
                            console.warn('[PATIENT URDU ASR RESTART EXCEPTION]', e);
                        }
                    }
                }, isMobile ? 150 : 300);
            }
        };

        patientUrduRecognition.start();
    } catch (err) {
        console.warn('[PATIENT URDU ASR START EXCEPTION]', err);
        patientUrduAsrActive = false;
    }
}

function stopPatientUrduAsr() {
    patientUrduAsrGeneration++;
    if (patientAsrStableTimer) {
        clearTimeout(patientAsrStableTimer);
        patientAsrStableTimer = null;
    }
    patientAsrActiveUtteranceText = '';
    patientAsrSegmentCandidates.clear();
    patientAsrLastSentIndex = -1;
    if (patientUrduRecognition) {
        try {
            patientUrduRecognition.abort();
        } catch (e) {}
        patientUrduRecognition = null;
    }
    patientUrduAsrActive = false;
}

let patientUtteranceCounter = 0;

async function sendPatientUrduAsrMessage(text) {
    if (micMuted) {
        console.log('[PATIENT URDU MESSAGE BLOCKED]', { reason: 'microphone-muted', text: text });
        return;
    }
    if (!text || !text.trim()) {
        console.log('[PATIENT URDU MESSAGE BLOCKED]', { reason: 'empty-text', text: text });
        return;
    }
    const cleanUrdu = cleanSubtitleText(text);
    if (!cleanUrdu) {
        console.log('[PATIENT URDU MESSAGE BLOCKED]', { reason: 'empty-text-after-clean', text: text });
        return;
    }

    const normCurrent = cleanUrdu.replace(/\s+/g, ' ').toLowerCase();
    const normLast = lastPatientAsrSentText.replace(/\s+/g, ' ').toLowerCase();
    const now = Date.now();

    // Deduplicate only exact identical sentences sent within a short 1200ms window (in-flight double trigger)
    if (normCurrent === normLast && (now - lastPatientAsrSentAt) < 1200) {
        console.log('[PATIENT ASR DUPLICATE SUPPRESSED]', {
            text: cleanUrdu,
            reason: 'identical-sentence-recently-sent'
        });
        return;
    }

    lastPatientAsrSentText = cleanUrdu;
    lastPatientAsrSentAt = now;
    const utteranceId = `utt-${AGORA_UID}-${++patientUtteranceCounter}-${now}`;

    console.log('[PATIENT ASR SEND ATTEMPT]', {
        fullRecognizedText: cleanUrdu,
        textBeingSent: cleanUrdu,
        transcript: cleanUrdu,
        target: 'doctor',
        messageType: 'patient_urdu_asr',
        utteranceId: utteranceId,
        speakerUid: Number(AGORA_UID)
    });

    const payload = {
        type: 'patient_urdu_asr',
        utteranceId: utteranceId,
        speakerUid: Number(AGORA_UID),
        sourceLanguage: 'ur-PK',
        text: cleanUrdu,
        isFinal: true,
        timestamp: now
    };

    try {
        const jsonStr = JSON.stringify(payload);
        const encoded = new TextEncoder().encode(jsonStr);
        await client.sendStreamMessage(encoded);
        console.log('[PATIENT ASR SEND SUCCESS]', payload);
        console.log('[PATIENT URDU MESSAGE SENT]', payload);
    } catch (err) {
        console.error('[PATIENT ASR SEND ERROR]', {
            error: err?.message || String(err),
            code: err?.code,
            payload: payload
        });
    }
}

function handlePatientUrduAsrMessage(msg) {
    if (!msg || msg.type !== 'patient_urdu_asr') return;
    const senderUid = msg.speakerUid || EXPECTED_REMOTE_UID;
    const speakerUid = senderUid;
    const isSelf = String(speakerUid) === String(AGORA_UID);

    console.log('[PATIENT URDU ASR RECEIVED BY DOCTOR]', {
        senderUid: senderUid,
        expectedRemoteUid: EXPECTED_REMOTE_UID,
        text: msg.text,
        ccActive: ccActive
    });

    console.log('[STT SELF CHECK]', {
        speakerUid: speakerUid,
        localUid: AGORA_UID,
        isSelf: isSelf,
        suppressed: isSelf
    });

    if (isSelf) {
        console.log('[PATIENT SELF CAPTION SUPPRESSED] Hiding self-speech caption on Patient screen');
        return;
    }

    if (!IS_DOCTOR) {
        return;
    }

    console.log('[PATIENT URDU MESSAGE RECEIVED]', {
        senderUid: senderUid,
        speakerUid: speakerUid,
        sourceLanguage: msg.sourceLanguage || 'ur-PK',
        text: msg.text
    });

    if (msg.text && msg.text.trim()) {
        translateAndDisplayEnglish(speakerUid, msg.text.trim());
    }
}

let subtitleRenderSeq = 0;

function displaySubtitle(speakerUid, text, isSystem = false, traceId = '') {
    const box     = document.getElementById('subtitleOverlay');
    const speaker = document.getElementById('subtitleSpeaker');
    const textEl  = document.getElementById('subtitleText');

    console.log('[SUBTITLE DOM TARGET]', {
        selector: '#subtitleOverlay',
        exists: !!box,
        elementId: box ? box.id : null,
        className: box ? box.className : null,
        role: CURRENT_USER_ROLE
    });

    if (!box || !speaker || !textEl) return;

    if (!ccActive && !isSystem) {
        console.log('[SUBTITLE DISPLAY BLOCKED]', { reason: 'viewer-cc-disabled' });
        if (box) box.style.display = 'none';
        return;
    }

    const isSelf = String(speakerUid) === String(AGORA_UID);

    if (isSelf) {
        console.log('[STT SELF CAPTION SUPPRESSED] Hiding self-speech caption on speaker screen');
        return;
    }

    // Final Patient Display Invariant for ur-PK:
    if (!IS_DOCTOR && VIEWER_SUBTITLE_LANGUAGE.startsWith('ur') && !isSystem) {
        const pureUrdu = extractPureUrduText(text);

        console.log('[PATIENT FINAL DOM VALIDATION]', {
            text: text,
            pureUrduExtracted: pureUrdu,
            containsUrdu: containsUrduScript(pureUrdu),
            containsLatin: containsLatinLetters(pureUrdu)
        });

        if (!pureUrdu || !containsUrduScript(pureUrdu) || containsLatinLetters(pureUrdu)) {
            console.log('[PATIENT SUBTITLE REJECTED]', { reason: 'latin-or-non-urdu-final-render', originalText: text, pureUrdu: pureUrdu });
            return;
        }
        text = pureUrdu;
    }

    console.log('[SUBTITLE DISPLAY DECISION]', {
        role: CURRENT_USER_ROLE,
        localUid: AGORA_UID,
        speakerUid: speakerUid,
        ccActive: ccActive,
        isSelf: isSelf,
        text: text
    });

    console.log('[SUBTITLE FINAL RENDER]', {
        role: CURRENT_USER_ROLE,
        localUid: AGORA_UID,
        speakerUid: speakerUid,
        viewerLanguage: VIEWER_SUBTITLE_LANGUAGE,
        text: text,
        source: isSystem ? 'system' : (isSelf ? 'self' : 'remote-translation')
    });

    console.log('[SUBTITLE DOM WRITE]', {
        traceId: traceId,
        role: CURRENT_USER_ROLE,
        viewerLanguage: VIEWER_SUBTITLE_LANGUAGE,
        speakerUid: speakerUid,
        finalText: text,
        timestamp: Date.now()
    });

    if (isSystem) {
        speaker.textContent = 'SYSTEM';
    } else if (isSelf) {
        speaker.textContent = IS_DOCTOR ? 'DOCTOR (YOU)' : 'PATIENT (YOU)';
    } else {
        const isDoctorSpeaker = IS_DOCTOR ? false : true;
        speaker.textContent = isDoctorSpeaker ? 'DOCTOR' : 'PATIENT';
    }

    console.log('[SUBTITLE DOM BEFORE WRITE]', {
        traceId: traceId,
        textContent: textEl.textContent,
        innerHTML: textEl.innerHTML
    });

    textEl.textContent = text;

    console.log('[SUBTITLE DOM AFTER WRITE]', {
        traceId: traceId,
        textContent: textEl.textContent,
        expectedText: text
    });

    const isRtl = ['ur-PK', 'ar-SA'].includes(VIEWER_SUBTITLE_LANGUAGE) || containsUrduScript(text);
    box.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    box.style.display = 'block';
    box.style.opacity = '1';
    box.style.visibility = 'visible';

    const styles = window.getComputedStyle(box);
    const rect = box.getBoundingClientRect();

    console.log('[SUBTITLE VISIBILITY]', {
        display: styles.display,
        visibility: styles.visibility,
        opacity: styles.opacity,
        color: styles.color,
        backgroundColor: styles.backgroundColor,
        zIndex: styles.zIndex,
        position: styles.position,
        width: box.offsetWidth,
        height: box.offsetHeight,
        clientRects: box.getClientRects().length
    });

    console.log('[SUBTITLE BOUNDING RECT]', {
        top: rect.top,
        left: rect.left,
        right: rect.right,
        bottom: rect.bottom,
        width: rect.width,
        height: rect.height,
        viewportWidth: window.innerWidth,
        viewportHeight: window.innerHeight
    });

    const currentRenderSeq = ++subtitleRenderSeq;

    if (subtitleClearTimer) clearTimeout(subtitleClearTimer);
    subtitleClearTimer = setTimeout(() => {
        if (currentRenderSeq === subtitleRenderSeq) {
            console.log('[SUBTITLE CLEAR CALLED]', { reason: 'timer-expiry', seq: currentRenderSeq, text: text });
            box.style.display = 'none';
        } else {
            console.log('[SUBTITLE CLEAR TIMER SUPPRESSED]', { reason: 'superseded-by-newer-caption', seq: currentRenderSeq, latestSeq: subtitleRenderSeq });
        }
    }, 5500);
}

document.getElementById('btnCC')?.addEventListener('click', async () => {
    if (ccStarting) return;
    const btn = document.getElementById('btnCC');
    const icon = document.getElementById('ccIcon');

    const prevCc = ccActive;
    ccActive = !ccActive;
    sessionStorage.setItem(CC_STORAGE_KEY, ccActive ? 'true' : 'false');

    console.log('[CC USER ACTION]', {
        role: CURRENT_USER_ROLE,
        requestedState: ccActive,
        previousState: prevCc,
        newState: ccActive,
        source: 'manual-click'
    });

    console.log('[CC STATE CHANGE]', {
        role: CURRENT_USER_ROLE,
        previousState: prevCc,
        newState: ccActive,
        changedBy: 'manual-click',
        micEnabled: !micMuted,
        sttRunning: !ccStarting
    });

    console.log('[CC VIEW STATE]', { role: CURRENT_USER_ROLE, enabled: ccActive });

    if (ccActive) {
        btn.className = 'control-btn cc-active';
        icon.textContent = 'CC';
        toastr.success('Live subtitles enabled');
        const statusMsg = VIEWER_SUBTITLE_LANGUAGE.startsWith('ur') 
            ? 'لائیو سب ٹائٹلز فعال ہیں — بات چیت سن رہے ہیں...'
            : `Live Subtitles Active (${APPOINTMENT_SUBTITLE_LANG_NAME}) — Listening for speech...`;
        displaySubtitle(AGORA_UID, statusMsg, true);

        if (IS_DOCTOR) {
            startTranslation(false).catch(err => console.warn('[STT START NOTICE]', err));
        }
    } else {
        btn.className = 'control-btn off';
        icon.textContent = 'CC';
        const box = document.getElementById('subtitleOverlay');
        if (box) box.style.display = 'none';
        toastr.info('Live subtitles disabled');
    }
});

async function startTranslation(force = false) {
    const btn = document.getElementById('btnCC');
    const icon = document.getElementById('ccIcon');
    
    ccStarting = true;
    btn.className = 'control-btn cc-starting';
    icon.innerHTML = '<i class="ti ti-loader spin"></i>';
    toastr.info('Starting live subtitles…');

    try {
        const res = await fetch(TRANSLATION_START_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                target_langs: [VIEWER_SUBTITLE_LANGUAGE],
                force: force
            })
        });

        const data = await res.json();
        console.log('[STT START RESPONSE]', { status: res.status, ok: res.ok, data: data });
        if (res.ok && data.active) {
            // Keep existing ccActive preference (do not force UI to active if Doctor initial preference was OFF)
            if (ccActive) {
                btn.className = 'control-btn cc-active';
                icon.textContent = 'CC';
                toastr.success('Live subtitles enabled');
                const statusMsg = VIEWER_SUBTITLE_LANGUAGE.startsWith('ur') 
                    ? 'لائیو سب ٹائٹلز فعال ہیں — بات چیت سن رہے ہیں...'
                    : `Live Subtitles Active (${APPOINTMENT_SUBTITLE_LANG_NAME}) — Listening for speech...`;
                displaySubtitle(AGORA_UID, statusMsg);
            } else {
                btn.className = 'control-btn off';
                icon.textContent = 'CC';
            }

            if (!IS_DOCTOR) {
                startPatientUrduAsr();
            }
        } else {
            throw new Error(data.error || data.message || 'Could not start subtitles');
        }
    } catch (err) {
        btn.className = ccActive ? 'control-btn cc-active' : 'control-btn off';
        icon.textContent = 'CC';
        toastr.error('Subtitle error: ' + err.message);
    } finally {
        ccStarting = false;
    }
}

async function stopTranslation() {
    const btn = document.getElementById('btnCC');
    const icon = document.getElementById('ccIcon');
    const subtitleOverlay = document.getElementById('subtitleOverlay');

    sessionStorage.removeItem(CC_STORAGE_KEY);

    if (!IS_DOCTOR) {
        stopPatientUrduAsr();
    }

    try {
        await fetch(TRANSLATION_STOP_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            }
        });
    } catch (err) {}

    ccActive = false;
    btn.className = 'control-btn off';
    icon.textContent = 'CC';
    if (subtitleOverlay) subtitleOverlay.style.display = 'none';
    toastr.info('Live subtitles disabled');
}

window.addEventListener('beforeunload', () => {
    if (ccActive) {
        const data = new FormData();
        data.append('_token', CSRF_TOKEN);
        navigator.sendBeacon(TRANSLATION_STOP_URL, data);
    }
});
</script>
@endpush