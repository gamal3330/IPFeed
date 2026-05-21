<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Feed Manager</title>
    <style>
        :root {
            --bg: #eef2f7;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --surface-muted: #eef2f7;
            --text: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-soft: #dbeafe;
            --success: #16a34a;
            --success-soft: #dcfce7;
            --danger: #dc2626;
            --danger-soft: #fee2e2;
            --warning: #d97706;
            --warning-soft: #fef3c7;
            --shadow: 0 14px 34px rgba(15, 23, 42, 0.10);
            --radius: 8px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: var(--bg);
        }

        a {
            color: inherit;
        }

        .auth-shell,
        .app-shell {
            width: min(1360px, calc(100% - 32px));
            margin: 0 auto;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 0;
        }

        .login-card {
            width: min(460px, 100%);
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .brand-mark {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: #ffffff;
            font-weight: 900;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            box-shadow: 0 14px 35px rgba(37, 99, 235, 0.32);
        }

        .brand-title,
        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        .brand-title {
            margin-bottom: 4px;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 0;
        }

        .brand-subtitle,
        .muted,
        .note {
            color: var(--muted);
        }

        .note {
            font-size: 13px;
            line-height: 1.8;
            margin: 12px 0 0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 13px 15px;
            color: var(--text);
            background: #ffffff;
            outline: none;
            font-size: 15px;
            transition: 160ms border-color ease, 160ms box-shadow ease, 160ms background ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: rgba(37, 99, 235, 0.75);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        textarea {
            min-height: 210px;
            resize: vertical;
            direction: ltr;
            text-align: left;
            font-family: Consolas, "Courier New", monospace;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            min-height: 44px;
            color: #ffffff;
            background: var(--primary);
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            text-decoration: none;
            transition: 160ms transform ease, 160ms box-shadow ease, 160ms background ease;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.24);
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            color: #1e293b;
            background: #ffffff;
            border: 1px solid var(--line);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: var(--surface-soft);
        }

        .btn-danger {
            background: var(--danger);
            box-shadow: none;
            padding: 9px 14px;
            min-height: 38px;
            border-radius: 8px;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-small {
            min-height: 36px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
        }

        .btn-warning {
            color: #ffffff;
            background: var(--warning);
            box-shadow: none;
        }

        .btn-warning:hover {
            background: #b45309;
        }

        .btn-block {
            width: 100%;
        }

        .btn .icon,
        .nav-link .icon,
        .alert .icon,
        .user-pill .icon,
        .filter-summary-title .icon {
            margin-top: 1px;
        }

        .app-shell {
            padding: 18px 0 44px;
        }

        .app-topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            padding: 12px;
            border: 1px solid rgba(226, 232, 240, 0.92);
            border-radius: var(--radius);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(18px);
        }

        .topbar-main,
        .topbar-actions,
        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-main {
            justify-content: space-between;
            gap: 14px;
        }

        .brand-lockup {
            min-width: 240px;
        }

        .app-mark {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: #1e293b;
            font-size: 13px;
            font-weight: 950;
        }

        .page-title {
            margin: 0;
            font-size: 20px;
            font-weight: 950;
            letter-spacing: 0;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }

        .app-nav {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            overflow-x: auto;
            scrollbar-width: thin;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 36px;
            padding: 8px 11px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: #334155;
            background: transparent;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: #f1f5f9;
        }

        .nav-link.active {
            color: #1d4ed8;
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .topbar-actions .btn-secondary {
            background: #ffffff;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }

        .icon {
            width: 17px;
            height: 17px;
            flex: 0 0 auto;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 18px;
            margin-top: 18px;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
        }

        .span-12 { grid-column: span 12; }
        .span-8 { grid-column: span 8; }
        .span-6 { grid-column: span 6; }
        .span-4 { grid-column: span 4; }
        .span-3 { grid-column: span 3; }

        .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .card h2 {
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 20px;
            letter-spacing: 0;
        }

        .card-head p {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            min-height: 136px;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            inset-inline-end: -42px;
            top: -42px;
            width: 120px;
            height: 120px;
            border-radius: 999px;
            background: var(--primary-soft);
        }

        .stat-label {
            position: relative;
            z-index: 1;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .stat-value {
            position: relative;
            z-index: 1;
            margin-top: 12px;
            color: #0f172a;
            font-size: 34px;
            font-weight: 950;
            letter-spacing: 0;
        }

        .stat-helper {
            position: relative;
            z-index: 1;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 12px;
        }

        .stats .stat-panel {
            min-height: 108px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .stats .stat-panel::after {
            display: none;
        }

        .stat-help {
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            margin-top: 18px;
            border-radius: 8px;
            line-height: 1.7;
            font-weight: 800;
        }

        .alert-success {
            color: #14532d;
            background: var(--success-soft);
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            color: #7f1d1d;
            background: var(--danger-soft);
            border: 1px solid #fecaca;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .bulk-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 14px;
            margin-bottom: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .bulk-actions .bulk-left,
        .bulk-actions .bulk-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .select-all-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: #334155;
            font-size: 13px;
            font-weight: 900;
            cursor: pointer;
        }

        .select-all-label input,
        .ip-select {
            width: auto;
            margin: 0;
            cursor: pointer;
        }

        .selected-count {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 5px 10px;
            border-radius: 999px;
            color: #1e293b;
            background: #e0f2fe;
            font-size: 12px;
            font-weight: 950;
        }

        .ip-search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-box {
            width: min(460px, 100%);
            position: relative;
        }

        .search-box input {
            padding-inline-start: 42px;
            margin: 0;
        }

        .ip-search-form .btn {
            min-height: 42px;
            padding: 10px 14px;
        }

        .search-box span {
            position: absolute;
            inset-inline-start: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
        }

        table {
            width: 100%;
            min-width: 1160px;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            text-align: right;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        th {
            position: sticky;
            top: 0;
            z-index: 2;
            color: #334155;
            background: #f8fafc;
            font-size: 12px;
            font-weight: 950;
            text-transform: uppercase;
            white-space: nowrap;
        }

        tbody tr {
            transition: 120ms background ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .ip {
            direction: ltr;
            text-align: left;
            font-family: Consolas, "Courier New", monospace;
            white-space: nowrap;
            color: #0f172a;
            font-weight: 800;
        }

        .ip-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 950;
            white-space: nowrap;
        }

        .badge-add {
            background: var(--success-soft);
            color: #166534;
        }

        .badge-delete {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .badge-check {
            background: var(--primary-soft);
            color: #1d4ed8;
        }

        .badge-user {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .badge-role-admin {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-role-operator {
            background: #ecfccb;
            color: #3f6212;
        }

        .badge-role-viewer {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-status-active {
            background: var(--success-soft);
            color: #166534;
        }

        .badge-status-disabled {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .badge-vt-danger {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .badge-vt-warning {
            background: var(--warning-soft);
            color: #92400e;
        }

        .badge-vt-success {
            background: var(--success-soft);
            color: #166534;
        }

        .badge-vt-muted {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-category-danger {
            background: #ffe4e6;
            color: #9f1239;
        }

        .badge-category-warning {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-category-spam {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-category-tor {
            background: #ede9fe;
            color: #5b21b6;
        }

        .filter-panel {
            display: grid;
            grid-template-columns: repeat(6, minmax(130px, 1fr));
            gap: 12px;
            align-items: end;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .filters-drawer {
            margin-bottom: 14px;
        }

        .filters-drawer summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            cursor: pointer;
            font-weight: 950;
            list-style: none;
        }

        .filters-drawer summary::-webkit-details-marker {
            display: none;
        }

        .filter-summary-title,
        .filter-summary-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-summary-meta {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
        }

        .filter-panel .form-group {
            margin-bottom: 0;
        }

        .filter-panel .search-wide {
            grid-column: span 2;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 14px;
            margin-bottom: 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .checkbox-row input {
            width: auto;
            margin: 4px 0 0;
        }

        .checkbox-row label {
            margin-bottom: 0;
            cursor: pointer;
        }

        .small-meta {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
        }

        .pagination-info {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .pagination-links {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            max-width: 100%;
            overflow-x: auto;
            padding-bottom: 2px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: #334155;
            background: #ffffff;
            text-decoration: none;
            font-weight: 900;
        }

        .page-link:hover,
        .page-link.active {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }

        .page-link.disabled {
            color: #94a3b8;
            background: #f8fafc;
            pointer-events: none;
        }

        .inline-form {
            margin: 0;
            display: inline;
        }

        .empty-state {
            padding: 34px 20px;
            color: var(--muted);
            text-align: center;
            background: #f8fafc;
        }

        .management-layout {
            display: grid;
            grid-template-columns: minmax(280px, 360px) 1fr;
            gap: 18px;
            align-items: start;
        }

        .user-table input,
        .user-table select {
            min-width: 150px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
        }

        .user-table input[type=checkbox] {
            width: auto;
            min-width: auto;
            transform: scale(1.12);
        }

        .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .country-list {
            display: grid;
            gap: 10px;
        }

        .country-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
        }

        .country-item strong {
            color: #0f172a;
        }

        .country-item span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .progress-panel {
            margin-top: 16px;
            border: 1px solid var(--line);
            background: rgba(15, 23, 42, 0.03);
            border-radius: 8px;
            padding: 14px;
        }

        .progress-panel[hidden] {
            display: none;
        }

        .progress-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 10px;
            color: var(--text);
            font-weight: 800;
        }

        .progress-track {
            height: 12px;
            border-radius: 999px;
            background: var(--line);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), var(--success));
            transition: width 0.25s ease;
        }

        .progress-details {
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .as-cell {
            min-width: 170px;
            direction: ltr;
            text-align: left;
        }

        .copy-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .copy-row input {
            direction: ltr;
            text-align: left;
            margin: 0;
            background: #f8fafc;
            font-family: Consolas, "Courier New", monospace;
        }

        .mini-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kbd {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 26px;
            padding: 0 8px;
            border-radius: 8px;
            color: #334155;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-family: Consolas, monospace;
            font-size: 12px;
            font-weight: 900;
        }


        .toolbar-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sort-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sort-form label {
            margin: 0;
            white-space: nowrap;
        }

        .sort-form select {
            min-width: 230px;
            margin: 0;
        }

        .user-create-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 12px;
            align-items: end;
            margin-bottom: 18px;
            padding: 14px;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .user-table input,
        .user-table select {
            min-width: 150px;
            margin: 0;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
        }

        .user-table .user-name {
            direction: ltr;
            text-align: left;
            font-weight: 950;
            white-space: nowrap;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 12px;
            margin: 14px 0;
        }

        .metadata-note {
            grid-column: span 2;
        }

        .status-active {
            background: var(--success-soft);
            color: #166534;
        }

        .status-disabled {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .role-meta {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
            margin-top: 6px;
        }

        .queue-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .queue-metric {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .queue-metric-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
        }

        .queue-metric-value {
            margin-top: 8px;
            color: #0f172a;
            font-size: 22px;
            font-weight: 950;
        }

        .health-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 14px;
        }

        .health-item {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
        }

        .health-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .health-group {
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
        }

        .health-name {
            margin-top: 3px;
            font-weight: 950;
        }

        .health-detail {
            color: #334155;
            font-family: Consolas, "Courier New", monospace;
            font-size: 12px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1024px) {
            .span-8,
            .span-6,
            .span-4,
            .span-3 {
                grid-column: span 12;
            }

            .topbar-main {
                align-items: flex-start;
                flex-direction: column;
            }

            .management-layout {
                grid-template-columns: 1fr;
            }

            .filter-panel,
            .metadata-grid,
            .stats {
                grid-template-columns: 1fr 1fr;
            }

            .filter-panel .search-wide {
                grid-column: span 2;
            }
        }

        @media (max-width: 640px) {
            .auth-shell,
            .app-shell {
                width: min(100% - 20px, 1360px);
            }

            .login-card,
            .card {
                border-radius: 8px;
                padding: 18px;
            }

            .topbar-actions,
            .copy-row,
            .toolbar,
            .toolbar-controls,
            .sort-form,
            .ip-search-form,
            .user-create-grid {
                width: 100%;
                align-items: stretch;
                flex-direction: column;
            }

            .topbar-actions .btn,
            .copy-row .btn,
            .mini-actions .btn,
            .bulk-actions .btn,
            .ip-search-form .btn,
            .search-box {
                width: 100%;
            }

            .bulk-actions,
            .bulk-actions .bulk-left,
            .bulk-actions .bulk-right {
                align-items: stretch;
                flex-direction: column;
            }

            .app-topbar {
                padding: 10px;
            }

            .brand-lockup,
            .topbar-actions {
                width: 100%;
            }

            .app-nav {
                padding-bottom: 2px;
            }

            .grid {
                gap: 12px;
            }

            .queue-metrics {
                grid-template-columns: 1fr 1fr;
            }

            .health-list {
                grid-template-columns: 1fr;
            }

            .filter-panel,
            .metadata-grid,
            .stats {
                grid-template-columns: 1fr;
            }

            .filter-panel .search-wide {
                grid-column: span 1;
            }

            .metadata-note {
                grid-column: span 1;
            }

            .table-wrap {
                border: 0;
                background: transparent;
            }

            table {
                min-width: 0;
                border-collapse: separate;
                border-spacing: 0 12px;
            }

            thead {
                display: none;
            }

            tbody tr {
                display: block;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #ffffff;
                overflow: hidden;
            }

            tbody tr:hover {
                background: #ffffff;
            }

            td {
                display: grid;
                grid-template-columns: minmax(96px, 38%) 1fr;
                gap: 10px;
                padding: 11px 12px;
                border-bottom: 1px solid var(--line);
                text-align: start;
            }

            td:last-child {
                border-bottom: 0;
            }

            td::before {
                content: attr(data-label);
                color: var(--muted);
                font-size: 11px;
                font-weight: 950;
            }

            td[colspan] {
                display: block;
            }

            td[colspan]::before {
                content: '';
            }

            .pagination {
                align-items: stretch;
                background: rgba(255, 255, 255, 0.96);
            }

            .pagination-info {
                width: 100%;
            }

            .pagination-links {
                flex-wrap: nowrap;
            }

            .page-link {
                min-width: 42px;
                flex: 0 0 auto;
            }
        }
    </style>
</head>
<body>
<?php if (!isLoggedIn()): ?>
    <main class="auth-shell">
        <section class="login-card" aria-label="تسجيل الدخول">
            <div class="login-brand">
                <div class="brand-mark">IP</div>
                <div>
                    <h1 class="brand-title">IP Feed Manager</h1>
                    <div class="brand-subtitle"></div>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= iconSvg('warning') ?> <span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <input id="username" type="text" name="username" placeholder="Username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input id="password" type="password" name="password" placeholder="••••••••••••" required>
                </div>

                <button class="btn btn-block" type="submit" name="login"><?= iconSvg('login') ?> دخول</button>
            </form>

            <p class="note"></p>
        </section>
    </main>
<?php elseif ($mustChangeDefaultAdminPassword): ?>
    <main class="auth-shell">
        <section class="login-card" aria-label="تغيير كلمة المرور الافتراضية">
            <div class="login-brand">
                <div class="brand-mark">IP</div>
                <div>
                    <h1 class="brand-title">تغيير كلمة مرور admin</h1>
                    <div class="brand-subtitle">يجب استبدال كلمة المرور الافتراضية قبل استخدام اللوحة.</div>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?= iconSvg('check') ?> <span><?= e($message) ?></span></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= iconSvg('warning') ?> <span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrfField() ?>
                <input type="hidden" name="force_password_change" value="1">

                <div class="form-group">
                    <label for="new_password">كلمة المرور الجديدة</label>
                    <input id="new_password" type="password" name="new_password" minlength="8" placeholder="8 أحرف على الأقل" required autofocus autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="new_password_confirm">تأكيد كلمة المرور الجديدة</label>
                    <input id="new_password_confirm" type="password" name="new_password_confirm" minlength="8" placeholder="أعد كتابتها" required autocomplete="new-password">
                </div>

                <div class="mini-actions">
                    <button class="btn" type="submit"><?= iconSvg('save') ?> حفظ كلمة المرور</button>
                    <a class="btn btn-secondary" href="?logout=1"><?= iconSvg('logout') ?> تسجيل خروج</a>
                </div>
            </form>

            <p class="note">لن تظهر لوحة الإدارة قبل تغيير كلمة المرور الافتراضية لحساب admin.</p>
        </section>
    </main>
<?php else: ?>
    <?php
        $currentPageTitle = appPageLabel($currentPage);
        $currentPageSubtitle = match ($currentPage) {
            'ips' => 'إضافة العناوين، فلترة القائمة، والإجراءات الجماعية.',
            'review' => 'مراجعة العناوين المرشحة للحذف قبل إزالة أي شيء من ips.txt.',
            'logs' => 'سجل العمليات ومحاولات الدخول في صفحة مستقلة.',
            'users' => 'إدارة الحسابات والصلاحيات.',
            'settings' => 'إعدادات التكامل والتشغيل.',
            'health' => 'فحص جاهزية النظام للمراقبة والتشغيل.',
            default => 'نظرة تشغيلية سريعة على القائمة والطابور.',
        };
        $navItems = [
            ['page' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['page' => 'ips', 'icon' => 'ips', 'label' => 'IPs'],
            ['page' => 'review', 'icon' => 'warning', 'label' => 'Review'],
            ['page' => 'logs', 'icon' => 'logs', 'label' => 'Logs'],
        ];
        if (canManageUsers($users)) {
            $navItems[] = ['page' => 'users', 'icon' => 'users', 'label' => 'Users'];
            $navItems[] = ['page' => 'settings', 'icon' => 'settings', 'label' => 'Settings'];
        }
        $navItems[] = ['page' => 'health', 'icon' => 'health', 'label' => 'Health'];
    ?>
    <main class="app-shell">
        <header class="app-topbar">
            <div class="topbar-main">
                <div class="brand-lockup">
                    <div class="app-mark">IP</div>
                    <div>
                        <h1 class="page-title"><?= e($currentPageTitle) ?></h1>
                        <div class="page-subtitle"><?= e($currentPageSubtitle) ?></div>
                    </div>
                </div>

                <div class="topbar-actions">
                    <div class="user-pill"><?= iconSvg('users') ?> <?= e($_SESSION['user']) ?> · <?= e($currentRoleLabel) ?></div>
                    <a class="btn btn-secondary btn-small" href="ips.txt" target="_blank" rel="noopener"><?= iconSvg('feed') ?> ips.txt</a>
                    <a class="btn btn-secondary btn-small" href="?logout=1"><?= iconSvg('logout') ?> خروج</a>
                </div>
            </div>

            <nav class="app-nav" aria-label="التنقل الرئيسي">
                <?php foreach ($navItems as $navItem): ?>
                    <?php $navPage = (string) $navItem['page']; ?>
                    <a class="nav-link <?= $currentPage === $navPage ? 'active' : '' ?>" href="?page=<?= e($navPage) ?>">
                        <?= iconSvg((string) $navItem['icon']) ?>
                        <span><?= e($navItem['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?= iconSvg('check') ?> <span><?= e($message) ?></span></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= iconSvg('warning') ?> <span><?= e($error) ?></span></div>
        <?php endif; ?>

        <?php if ($currentPage === 'dashboard'): ?>
        <section class="grid" aria-label="الإحصائيات الرئيسية">
            <div class="card stat-card span-3">
                <div class="stat-label">عدد IPs الحالي</div>
                <div class="stat-value"><?= number_format(count($existingIps)) ?></div>
                <div class="stat-helper">العناوين الموجودة في ips.txt</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">عمليات الإضافة</div>
                <div class="stat-value"><?= number_format($addCount) ?></div>
                <div class="stat-helper">حسب سجل العمليات</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">عمليات الحذف</div>
                <div class="stat-value"><?= number_format($deleteCount) ?></div>
                <div class="stat-helper">حسب سجل العمليات</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">VirusTotal</div>
                <div class="stat-value"><?= number_format($vtDangerCount + $vtSuspiciousCount) ?></div>
                <div class="stat-helper">خطير أو مشبوه حسب آخر السجلات</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">حظر منتهي</div>
                <div class="stat-value"><?= number_format($expiredIpCount) ?></div>
                <div class="stat-helper"><a href="?page=review&review_mode=expired">عناوين تحتاج مراجعة أو حذف</a></div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">المستخدمون النشطون</div>
                <div class="stat-value"><?= number_format($activeUsersCount) ?></div>
                <div class="stat-helper">حسب قاعدة SQLite</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">آخر تحديث</div>
                <div class="stat-value" style="font-size: 20px; letter-spacing: 0; direction: ltr; text-align: right;"><?= e($lastUpdate) ?></div>
                <div class="stat-helper">بتوقيت Asia/Aden</div>
            </div>

            <div class="card stat-card span-3">
                <div class="stat-label">تقييد الدخول</div>
                <div class="stat-value" style="font-size: 20px; letter-spacing: 0;"><?= $countryRestrictionEnabled ? 'الأردن واليمن فقط' : 'غير مفعل' ?></div>
                <div class="stat-helper">بلد الزائر: <?= e($visitorAccess['country_code'] ?? 'Unknown') ?></div>
            </div>
        </section>

        <section class="grid" id="vtQueueSection">
            <div class="card span-12">
                <div class="toolbar">
                    <div class="card-head" style="margin-bottom: 0;">
                        <div>
                            <h2>طابور فحص VirusTotal</h2>
                            <p>تتم معالجة عناوين IP تدريجياً دون تعليق الصفحة، مع حفظ آخر نتيجة لكل عنوان.</p>
                        </div>
                    </div>

                    <div class="mini-actions">
                        <button id="vtQueueRunBtn" class="btn btn-secondary" type="button" onclick="processVirusTotalQueueOnce()" <?= $vtApiKey === '' || !canCheckVirusTotal($users) ? 'disabled' : '' ?>><?= iconSvg('scan') ?> تشغيل دفعة</button>
                    </div>
                </div>

                <div class="queue-metrics">
                    <div class="queue-metric">
                        <div class="queue-metric-label">منتظر</div>
                        <div id="vtQueueQueued" class="queue-metric-value"><?= number_format((int) ($vtQueueStats['queued'] ?? 0)) ?></div>
                    </div>
                    <div class="queue-metric">
                        <div class="queue-metric-label">جاري</div>
                        <div id="vtQueueProcessing" class="queue-metric-value"><?= number_format((int) ($vtQueueStats['processing'] ?? 0)) ?></div>
                    </div>
                    <div class="queue-metric">
                        <div class="queue-metric-label">مكتمل</div>
                        <div id="vtQueueCompleted" class="queue-metric-value"><?= number_format((int) ($vtQueueStats['completed'] ?? 0)) ?></div>
                    </div>
                    <div class="queue-metric">
                        <div class="queue-metric-label">فشل</div>
                        <div id="vtQueueFailed" class="queue-metric-value"><?= number_format((int) ($vtQueueStats['failed'] ?? 0)) ?></div>
                    </div>
                </div>

                <div id="vtQueueMessage" class="note">آخر نتيجة محفوظة تعتبر حديثة لمدة <?= e(secondsToHumanArabic((int) $vtResultFreshTtlSeconds)) ?>، ولن يعاد فحصها خلال هذه المدة.</div>

                <div class="table-wrap" style="margin-top: 14px;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>IP</th>
                                <th>الحالة</th>
                                <th>السبب</th>
                                <th>المستخدم</th>
                                <th>المحاولات</th>
                                <th>آخر خطأ</th>
                                <th>آخر تحديث</th>
                            </tr>
                        </thead>
                        <tbody id="vtQueueRows">
                            <?php if (empty($recentVtQueue)): ?>
                                <tr>
                                    <td colspan="8"><div class="empty-state">لا توجد مهام VirusTotal في الطابور حالياً.</div></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($recentVtQueue as $queueRow): ?>
                                <tr>
                                    <td><?= (int) ($queueRow['id'] ?? 0) ?></td>
                                    <td class="ip"><?= e($queueRow['ip'] ?? '') ?></td>
                                    <td><span class="badge <?= e(vtQueueBadgeClass((string) ($queueRow['status'] ?? ''))) ?>"><?= e(vtQueueStatusLabel((string) ($queueRow['status'] ?? ''))) ?></span></td>
                                    <td><?= e($queueRow['reason'] ?? '') ?></td>
                                    <td><?= e($queueRow['user'] ?? '') ?></td>
                                    <td><?= number_format((int) ($queueRow['attempts'] ?? 0)) ?></td>
                                    <td><?= e($queueRow['last_error'] ?? '') ?></td>
                                    <td class="ip"><?= e(($queueRow['completed_at'] ?? '') ?: (($queueRow['started_at'] ?? '') ?: ($queueRow['created_at'] ?? ''))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="note">إذا كانت الصفحة مفتوحة ولديك صلاحية الفحص، سيعالج المتصفح الطابور تلقائياً بمعدل متدرج يحترم حدود VirusTotal.</p>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($currentPage === 'ips'): ?>
        <section class="grid">
            <div class="card span-8">
                <div class="card-head">
                    <div>
                        <h2>إضافة IP واحد أو مجموعة IPs</h2>
                        <p>ألصق العناوين مفصولة بأسطر، مسافات، فواصل، أو فاصلة منقوطة.</p>
                    </div>
                    <span class="kbd">IPv4</span>
                </div>

                <?php if (canModifyIps($users)): ?>
                <form id="addIpsForm" method="post">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="ips">قائمة عناوين IP</label>
                        <textarea id="ips" name="ips" placeholder="1.1.1.1&#10;8.8.8.8&#10;37.187.109.70"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="reason">سبب الإضافة</label>
                        <input id="reason" type="text" name="reason" placeholder="مثال: Brute Force / Scan / Spam / TOR / Proxy">
                    </div>

                    <div class="metadata-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="category">التصنيف</label>
                            <select id="category" name="category">
                                <?php foreach (allowedIpCategories() as $categoryValue => $categoryLabel): ?>
                                    <option value="<?= e($categoryValue) ?>"><?= e($categoryLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="expires_at">تاريخ انتهاء الحظر</label>
                            <input id="expires_at" type="date" name="expires_at">
                        </div>
                        <div class="form-group metadata-note" style="margin-bottom: 0;">
                            <label for="metadata_note">ملاحظة إدارية</label>
                            <input id="metadata_note" type="text" name="metadata_note" maxlength="500" placeholder="اختياري">
                        </div>
                    </div>

                    <div class="checkbox-row">
                        <input id="check_virustotal" type="checkbox" name="check_virustotal" value="1" <?= $vtApiKey === '' ? 'disabled' : 'checked' ?>>
                        <div>
                            <label for="check_virustotal">فحص VirusTotal عند الإضافة</label>
                            <div class="small-meta">
                                <?= $vtApiKey === '' ? 'غير مفعل حالياً: أضف المفتاح من لوحة المدير أو اضبط VT_API_KEY على الخادم.' : 'سيتم جلب نتيجة السمعة، وعدد المحركات، و ASN/AS Owner مثل AS32934 (Facebook, Inc.).' ?>
                            </div>
                        </div>
                    </div>

                    <div class="mini-actions">
                        <button id="addIpsSubmit" class="btn" type="submit" name="add_ips"><?= iconSvg('add') ?> حفظ الإضافات</button>
                        <button class="btn btn-secondary" type="button" onclick="document.getElementById('ips').value=''; document.getElementById('reason').value=''; document.getElementById('expires_at').value=''; document.getElementById('metadata_note').value='';"><?= iconSvg('clear') ?> مسح الحقول</button>
                    </div>
                </form>

                <div id="addProgressPanel" class="progress-panel" hidden>
                    <div class="progress-top">
                        <span>جاري إضافة العناوين على دفعات...</span>
                        <span id="addProgressPercent">0%</span>
                    </div>
                    <div class="progress-track" aria-label="تقدم الإضافة">
                        <div id="addProgressFill" class="progress-fill"></div>
                    </div>
                    <div id="addProgressDetails" class="progress-details">التحضير للإضافة...</div>
                </div>

                <p class="note">سيتم تجاهل القيم غير الصحيحة، حذف التكرارات تلقائياً، ثم تسجيل الدولة والمدينة واسم المزود وإضافة فحص VirusTotal إلى الطابور عند تفعيله.</p>
                <?php else: ?>
                    <div class="empty-state" style="border-radius: 8px;">حسابك بصلاحية مشاهدة فقط، لذلك لا يمكنه إضافة IPs أو تعديل القائمة.</div>
                <?php endif; ?>
            </div>

            <aside class="card span-4">
                <div class="card-head">
                    <div>
                        <h2>رابط FortiGate Feed</h2>
                        <p>استخدم هذا الرابط كمصدر خارجي لقائمة الحظر.</p>
                    </div>
                </div>

                <div class="copy-row">
                    <input id="feedLink" type="text" value="<?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '/') . '/ips.txt') ?>" readonly>
                    <button class="btn btn-secondary" type="button" onclick="copyFeedLink()"><?= iconSvg('feed') ?> نسخ</button>
                </div>

                <p class="note">تأكد من أن الملف <strong>ips.txt</strong> قابل للقراءة من FortiGate، وأن صلاحيات الكتابة مضبوطة للوحة فقط.</p>

                <p class="note"><strong>حالة VirusTotal:</strong> <?= $vtApiKey === '' ? 'غير مفعل؛ أضف المفتاح من لوحة المدير.' : 'مفعل من خلال ' . e((string) ($vtConfig['source_label'] ?? 'إعداد محفوظ')) . ' — ' . e((string) ($vtConfig['masked'] ?? '')) ?></p>

                <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

                <h3 style="margin-bottom: 12px;">أكثر الدول في قائمة IPs المشبوهة</h3>
                <div class="country-list">
                    <?php if (empty($topCountries)): ?>
                        <div class="empty-state" style="border-radius: 8px;">لا توجد بيانات دول كافية للقائمة الحالية حتى الآن.</div>
                    <?php else: ?>
                        <?php foreach ($topCountries as $country => $count): ?>
                            <div class="country-item">
                                <strong><?= e($country) ?></strong>
                                <span><?= number_format($count) ?> IP</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p class="note" style="margin-top: 12px;">هذه الإحصائية محسوبة من عناوين <strong>ips.txt</strong> الموجودة حالياً، وليست من المستخدمين أو عدد عمليات الإضافة. المعروف جغرافياً: <?= number_format($countryStatsMeta['known'] ?? 0) ?> من <?= number_format($countryStatsMeta['total'] ?? 0) ?> IP.</p>
            </aside>
        </section>

        <section class="grid">
            <div class="card span-12">
                <div class="toolbar">
                    <div class="card-head" style="margin-bottom: 0;">
                        <div>
                            <h2>جميع IPs الموجودة في ips.txt</h2>
                            <p>بحث وفلاتر وإدارة جماعية للتصنيف، الانتهاء، الحذف، والتصدير.</p>
                        </div>
                    </div>
                </div>

                <?php $activeIpFilterCount = count(array_filter($ipFilters, static fn ($value): bool => (string) $value !== '')); ?>
                <details class="filters-drawer" <?= $activeIpFilterCount > 0 ? 'open' : '' ?>>
                    <summary>
                        <span class="filter-summary-title"><?= iconSvg('filter') ?> الفلاتر والبحث</span>
                        <span class="filter-summary-meta"><?= number_format($activeIpFilterCount) ?> فلتر نشط · <?= number_format($ipTotalRows) ?> نتيجة</span>
                    </summary>

                    <form class="filter-panel" method="get">
                        <input type="hidden" name="page" value="ips">
                        <input type="hidden" name="ip_page" value="1">
                        <input type="hidden" name="log_page" value="<?= (int) $logPage ?>">
                        <div class="form-group search-wide">
                            <label for="ipSearch">بحث IP</label>
                            <input type="search" id="ipSearch" name="ip_query" value="<?= e($ipSearchQuery) ?>" placeholder="مثال: 8.8 أو 192.168.1.10" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="ipCountry">الدولة</label>
                            <select id="ipCountry" name="country">
                                <option value="">كل الدول</option>
                                <?php foreach ($filterCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= ($ipFilters['country'] ?? '') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipVtStatus">VirusTotal</label>
                            <select id="ipVtStatus" name="vt_status">
                                <option value="">كل الحالات</option>
                                <?php foreach (['خطير', 'مشبوه', 'نظيف', 'مؤجل', 'في الطابور', 'نتيجة حديثة'] as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>" <?= ($ipFilters['vt_status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                                <?php endforeach; ?>
                                <option value="__unscanned" <?= ($ipFilters['vt_status'] ?? '') === '__unscanned' ? 'selected' : '' ?>>لم يتم الفحص</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipAsn">ASN</label>
                            <select id="ipAsn" name="asn">
                                <option value="">كل ASN</option>
                                <?php foreach ($filterAsns as $asn): ?>
                                    <option value="<?= e($asn) ?>" <?= ($ipFilters['asn'] ?? '') === (string) $asn ? 'selected' : '' ?>>AS<?= e($asn) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipUser">المستخدم</label>
                            <select id="ipUser" name="user">
                                <option value="">كل المستخدمين</option>
                                <?php foreach ($filterUsers as $userOption): ?>
                                    <option value="<?= e($userOption) ?>" <?= ($ipFilters['user'] ?? '') === $userOption ? 'selected' : '' ?>><?= e($userOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipCategoryFilter">التصنيف</label>
                            <select id="ipCategoryFilter" name="category">
                                <option value="">كل التصنيفات</option>
                                <?php foreach (allowedIpCategories() as $categoryValue => $categoryLabel): ?>
                                    <option value="<?= e($categoryValue) ?>" <?= ($ipFilters['category'] ?? '') === $categoryValue ? 'selected' : '' ?>><?= e($categoryLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipExpiry">الانتهاء</label>
                            <select id="ipExpiry" name="expiry">
                                <option value="">كل الحالات</option>
                                <option value="permanent" <?= ($ipFilters['expiry'] ?? '') === 'permanent' ? 'selected' : '' ?>>دائم</option>
                                <option value="temporary" <?= ($ipFilters['expiry'] ?? '') === 'temporary' ? 'selected' : '' ?>>مؤقت نشط</option>
                                <option value="today" <?= ($ipFilters['expiry'] ?? '') === 'today' ? 'selected' : '' ?>>ينتهي اليوم</option>
                                <option value="expiring_7" <?= ($ipFilters['expiry'] ?? '') === 'expiring_7' ? 'selected' : '' ?>>ينتهي خلال 7 أيام</option>
                                <option value="expired" <?= ($ipFilters['expiry'] ?? '') === 'expired' ? 'selected' : '' ?>>منتهي</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ipDateFrom">من تاريخ</label>
                            <input id="ipDateFrom" type="date" name="date_from" value="<?= e($ipFilters['date_from'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ipDateTo">إلى تاريخ</label>
                            <input id="ipDateTo" type="date" name="date_to" value="<?= e($ipFilters['date_to'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ipSort">الفرز</label>
                            <select id="ipSort" name="ip_sort">
                                <option value="natural" <?= $ipSort === 'natural' ? 'selected' : '' ?>>الترتيب الطبيعي حسب IP</option>
                                <option value="severity_desc" <?= $ipSort === 'severity_desc' ? 'selected' : '' ?>>الأعلى خطورة أولاً</option>
                                <option value="severity_asc" <?= $ipSort === 'severity_asc' ? 'selected' : '' ?>>الأقل خطورة أولاً</option>
                                <option value="malicious_desc" <?= $ipSort === 'malicious_desc' ? 'selected' : '' ?>>أعلى VT Score أولاً</option>
                                <option value="malicious_asc" <?= $ipSort === 'malicious_asc' ? 'selected' : '' ?>>أقل VT Score أولاً</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button class="btn btn-secondary" type="submit"><?= iconSvg('search') ?> تطبيق</button>
                        </div>
                        <div class="form-group">
                            <a class="btn btn-secondary" href="<?= e(currentUrlWithout(['ip_query', 'country', 'vt_status', 'asn', 'user', 'category', 'expiry', 'date_from', 'date_to', 'ip_page', 'log_page'])) ?>"><?= iconSvg('clear') ?> مسح</a>
                        </div>
                    </form>
                </details>

                <form id="bulkVtForm" method="post" onsubmit="return confirmBulkAction(event);">
                    <?= csrfField() ?>
                    <input id="selectAllIpsScope" type="hidden" name="select_all_ips" value="0">
                    <input type="hidden" name="bulk_ip_query" value="<?= e($ipSearchQuery) ?>">
                    <input type="hidden" name="bulk_country" value="<?= e($ipFilters['country'] ?? '') ?>">
                    <input type="hidden" name="bulk_vt_status" value="<?= e($ipFilters['vt_status'] ?? '') ?>">
                    <input type="hidden" name="bulk_asn" value="<?= e($ipFilters['asn'] ?? '') ?>">
                    <input type="hidden" name="bulk_user" value="<?= e($ipFilters['user'] ?? '') ?>">
                    <input type="hidden" name="bulk_category" value="<?= e($ipFilters['category'] ?? '') ?>">
                    <input type="hidden" name="bulk_expiry" value="<?= e($ipFilters['expiry'] ?? '') ?>">
                    <input type="hidden" name="bulk_date_from" value="<?= e($ipFilters['date_from'] ?? '') ?>">
                    <input type="hidden" name="bulk_date_to" value="<?= e($ipFilters['date_to'] ?? '') ?>">
                    <input type="hidden" id="exportFormat" name="export_format" value="txt">
                </form>

                <div class="bulk-actions">
                    <div class="bulk-left">
                        <label class="select-all-label" for="selectAllIps">
                            <input id="selectAllIps" type="checkbox" onchange="toggleIpSelection(this)" <?= isLoggedIn() && $ipTotalRows > 0 ? '' : 'disabled' ?>>
                            تحديد الصفحة الحالية
                        </label>
                        <label class="select-all-label" for="selectAllAllIps">
                            <input id="selectAllAllIps" type="checkbox" onchange="toggleAllPagesSelection(this)" <?= isLoggedIn() && $ipTotalRows > 0 ? '' : 'disabled' ?>>
                            تحديد كل النتائج المفلترة، جميع الصفحات
                        </label>
                        <span class="selected-count">المحدد: <span id="selectedIpCount">0</span></span>
                    </div>

                    <div class="bulk-right">
                        <button class="btn btn-secondary" type="submit" name="bulk_check_vt" value="selected" form="bulkVtForm" <?= $vtApiKey === '' || $ipTotalRows === 0 || !canCheckVirusTotal($users) ? 'disabled' : '' ?>><?= iconSvg('scan') ?> فحص VT</button>
                        <button class="btn btn-secondary" type="submit" name="bulk_export_ips" value="selected" form="bulkVtForm" onclick="document.getElementById('exportFormat').value='csv'" <?= $ipTotalRows === 0 ? 'disabled' : '' ?>><?= iconSvg('download') ?> CSV</button>
                        <button class="btn btn-secondary" type="submit" name="bulk_export_ips" value="selected" form="bulkVtForm" onclick="document.getElementById('exportFormat').value='txt'" <?= $ipTotalRows === 0 ? 'disabled' : '' ?>><?= iconSvg('download') ?> TXT</button>
                        <button class="btn btn-danger" type="submit" name="bulk_delete_ips" value="selected" form="bulkVtForm" <?= $ipTotalRows === 0 || !canModifyIps($users) ? 'disabled' : '' ?>><?= iconSvg('trash') ?> حذف المحدد</button>
                    </div>
                </div>

                <?php if (canModifyIps($users)): ?>
                    <div class="bulk-actions">
                        <div class="bulk-left">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="bulkSetCategory">تصنيف المحدد</label>
                                <select id="bulkSetCategory" name="bulk_set_category" form="bulkVtForm">
                                    <?php foreach (allowedIpCategories() as $categoryValue => $categoryLabel): ?>
                                        <option value="<?= e($categoryValue) ?>"><?= e($categoryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="bulkSetExpiresAt">انتهاء الحظر</label>
                                <input id="bulkSetExpiresAt" type="date" name="bulk_set_expires_at" form="bulkVtForm">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="bulkSetNote">ملاحظة</label>
                                <input id="bulkSetNote" type="text" name="bulk_set_note" form="bulkVtForm" maxlength="500" placeholder="اختياري">
                            </div>
                        </div>
                        <div class="bulk-right">
                            <button class="btn" type="submit" name="bulk_metadata_ips" value="selected" form="bulkVtForm" <?= $ipTotalRows === 0 ? 'disabled' : '' ?>><?= iconSvg('save') ?> تحديث التصنيف/الانتهاء</button>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="note">الفرز الحالي: <strong><?= e(ipSortLabel($ipSort)) ?></strong>. النتائج المعروضة بعد الفلاتر: <?= number_format($ipTotalRows) ?> من أصل <?= number_format(count($existingIps)) ?> IP في ips.txt. فحص VirusTotal الجماعي يضيف أول <?= number_format($bulkScanLimit) ?> IP فقط في الطلب الواحد كحماية من الازدحام.</p>

                <div class="table-wrap">
                    <table id="ipsTable">
                        <thead>
                            <tr>
                                <th>تحديد</th>
                                <th>#</th>
                                <th>IP</th>
                                <th>التصنيف</th>
                                <th>الانتهاء</th>
                                <th>VirusTotal</th>
                                <th>VT Score</th>
                                <th>ASN / AS Owner</th>
                                <th>الدولة</th>
                                <th>المستخدم</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagedIpRows)): ?>
                                <tr>
                                    <td colspan="11">
                                        <div class="empty-state"><?= $ipTotalRows === 0 && count($existingIps) > 0 ? 'لا توجد نتائج مطابقة للفلاتر الحالية.' : 'لا توجد IPs حالياً.' ?></div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($pagedIpRows as $offset => $ipRow): ?>
                                <?php
                                    $ip = (string) ($ipRow['ip'] ?? '');
                                    $vtRow = $ipRow['vt_row'] ?? ($latestVtByIp[$ip] ?? null);
                                    $category = (string) ($ipRow['category'] ?? 'manual');
                                    $expiresAt = (string) ($ipRow['expires_at'] ?? '');
                                ?>
                                <tr>
                                    <td><input class="ip-select" type="checkbox" name="selected_ips[]" value="<?= e($ip) ?>" form="bulkVtForm" onchange="updateSelectedCount()" <?= isLoggedIn() ? '' : 'disabled' ?>></td>
                                    <td><?= (($ipPage - 1) * $rowsPerPage) + $offset + 1 ?></td>
                                    <td class="ip"><span class="ip-chip"><?= e($ip) ?></span></td>
                                    <td>
                                        <span class="badge <?= e(ipCategoryBadgeClass($category)) ?>"><?= e(ipCategoryLabel($category)) ?></span>
                                        <?php if (!empty($ipRow['note'])): ?>
                                            <div class="small-meta"><?= e($ipRow['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= e(expirationBadgeClass($expiresAt)) ?>"><?= e(expirationLabel($expiresAt)) ?></span></td>
                                    <td>
                                        <?php if ($vtRow): ?>
                                            <span class="badge <?= e(vtBadgeClass((string) ($vtRow['vt_status'] ?? ''))) ?>"><?= e($vtRow['vt_status'] ?? 'غير معروف') ?></span>
                                            <?php if (!empty($vtRow['vt_error'])): ?>
                                                <div class="small-meta"><?= e($vtRow['vt_error']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-vt-muted">لم يتم الفحص</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ip">
                                        <?php if ($vtRow && !empty($vtRow['vt_link'])): ?>
                                            <a href="<?= e($vtRow['vt_link']) ?>" target="_blank" rel="noopener"><?= e(($vtRow['vt_malicious'] ?? 0) . ' / ' . ($vtRow['vt_total'] ?? 0)) ?></a>
                                            <div class="small-meta">Suspicious: <?= e($vtRow['vt_suspicious'] ?? 0) ?></div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="as-cell"><?= e($vtRow ? vtAsText($vtRow) : '-') ?></td>
                                    <td><?= e($ipRow['country'] ?? 'Unknown') ?></td>
                                    <td>
                                        <?= e($ipRow['user'] ?? '-') ?>
                                        <?php if (!empty($ipRow['added_at'])): ?>
                                            <div class="small-meta ip"><?= e($ipRow['added_at']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (canModifyIps($users)): ?>
                                            <div class="mini-actions">
                                                <form method="post" class="inline-form">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="check_vt_ip" value="<?= e($ip) ?>">
                                                    <button type="submit" class="btn btn-secondary" <?= $vtApiKey === '' ? 'disabled' : '' ?>><?= iconSvg('scan') ?> فحص VT</button>
                                                </form>
                                                <form method="post" class="inline-form" onsubmit="return confirm('هل تريد حذف هذا IP؟');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="delete_ip" value="<?= e($ip) ?>">
                                                    <button type="submit" class="btn btn-danger"><?= iconSvg('trash') ?> حذف</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-vt-muted">مشاهدة فقط</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" aria-label="تقسيم صفحات IPs">
                    <div class="pagination-info">
                        عرض <?= $ipTotalRows === 0 ? 0 : (($ipPage - 1) * $rowsPerPage) + 1 ?> - <?= min($ipPage * $rowsPerPage, $ipTotalRows) ?> من <?= number_format($ipTotalRows) ?> IP
                    </div>
                    <div class="pagination-links">
                        <a class="page-link <?= $ipPage <= 1 ? 'disabled' : '' ?>" href="<?= e(pageUrl('ip_page', $ipPage - 1)) ?>">السابق</a>
                        <?php for ($p = max(1, $ipPage - 2); $p <= min($ipTotalPages, $ipPage + 2); $p++): ?>
                            <a class="page-link <?= $p === $ipPage ? 'active' : '' ?>" href="<?= e(pageUrl('ip_page', $p)) ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?= $ipPage >= $ipTotalPages ? 'disabled' : '' ?>" href="<?= e(pageUrl('ip_page', $ipPage + 1)) ?>">التالي</a>
                    </div>
                </div>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($currentPage === 'review'): ?>
        <section class="grid" aria-label="مراجعة IPs قبل الحذف">
            <div class="card stat-card span-3">
                <div class="stat-label">كل المرشحين</div>
                <div class="stat-value"><?= number_format((int) ($reviewCounts['all'] ?? 0)) ?></div>
                <div class="stat-helper">حسب قواعد المراجعة الحالية</div>
            </div>
            <div class="card stat-card span-3">
                <div class="stat-label">منتهي</div>
                <div class="stat-value"><?= number_format((int) ($reviewCounts['expired'] ?? 0)) ?></div>
                <div class="stat-helper">تاريخ انتهاء الحظر مضى</div>
            </div>
            <div class="card stat-card span-3">
                <div class="stat-label">نظيف</div>
                <div class="stat-value"><?= number_format((int) ($reviewCounts['clean'] ?? 0)) ?></div>
                <div class="stat-helper">VT نظيف منذ 7 أيام أو أكثر</div>
            </div>
            <div class="card stat-card span-3">
                <div class="stat-label">قديم/غير مفحوص</div>
                <div class="stat-value"><?= number_format(((int) ($reviewCounts['unscanned'] ?? 0)) + ((int) ($reviewCounts['old'] ?? 0))) ?></div>
                <div class="stat-helper">مرشح للمراجعة اليدوية</div>
            </div>

            <div class="card span-12">
                <div class="toolbar">
                    <div class="card-head" style="margin-bottom: 0;">
                        <div>
                            <h2>مراجعة قبل الحذف</h2>
                            <p>هذه الصفحة لا تحذف تلقائياً؛ تعرض فقط عناوين لها سبب واضح للمراجعة ثم تترك قرار الحذف لك.</p>
                        </div>
                    </div>
                    <div class="pagination-links">
                        <?php foreach (['all', 'expired', 'clean', 'unscanned', 'old'] as $modeValue): ?>
                            <a class="page-link <?= $reviewMode === $modeValue ? 'active' : '' ?>" href="?page=review&review_mode=<?= e($modeValue) ?>">
                                <?= e(ipReviewModeLabel($modeValue)) ?> · <?= number_format((int) ($reviewCounts[$modeValue] ?? 0)) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form id="reviewBulkForm" method="post" onsubmit="return confirmBulkAction(event);">
                    <?= csrfField() ?>
                    <input type="hidden" id="reviewExportFormat" name="export_format" value="txt">
                </form>

                <div class="bulk-actions">
                    <div class="bulk-left">
                        <label class="select-all-label" for="selectAllIps">
                            <input id="selectAllIps" type="checkbox" onchange="toggleIpSelection(this)" <?= isLoggedIn() && $reviewTotalRows > 0 ? '' : 'disabled' ?>>
                            تحديد الصفحة الحالية
                        </label>
                        <span class="selected-count">المحدد: <span id="selectedIpCount">0</span></span>
                    </div>
                    <div class="bulk-right">
                        <button class="btn btn-secondary" type="submit" name="bulk_export_ips" value="selected" form="reviewBulkForm" onclick="document.getElementById('reviewExportFormat').value='csv'" <?= $reviewTotalRows === 0 ? 'disabled' : '' ?>><?= iconSvg('download') ?> CSV</button>
                        <button class="btn btn-secondary" type="submit" name="bulk_export_ips" value="selected" form="reviewBulkForm" onclick="document.getElementById('reviewExportFormat').value='txt'" <?= $reviewTotalRows === 0 ? 'disabled' : '' ?>><?= iconSvg('download') ?> TXT</button>
                        <button class="btn btn-danger" type="submit" name="bulk_delete_ips" value="selected" form="reviewBulkForm" <?= $reviewTotalRows === 0 || !canModifyIps($users) ? 'disabled' : '' ?>><?= iconSvg('trash') ?> حذف المحدد</button>
                    </div>
                </div>

                <p class="note">الفلتر الحالي: <strong><?= e(ipReviewModeLabel($reviewMode)) ?></strong>. النتائج المعروضة: <?= number_format($reviewTotalRows) ?> من أصل <?= number_format((int) ($reviewCounts['all'] ?? 0)) ?> مرشح.</p>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>تحديد</th>
                                <th>#</th>
                                <th>IP</th>
                                <th>سبب المراجعة</th>
                                <th>التصنيف</th>
                                <th>الانتهاء</th>
                                <th>VirusTotal</th>
                                <th>الدولة</th>
                                <th>المستخدم/التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagedReviewRows)): ?>
                                <tr>
                                    <td colspan="9"><div class="empty-state">لا توجد عناوين مرشحة للمراجعة حسب هذا الفلتر.</div></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($pagedReviewRows as $offset => $reviewRow): ?>
                                <?php
                                    $ip = (string) ($reviewRow['ip'] ?? '');
                                    $vtRow = $reviewRow['vt_row'] ?? ($latestVtByIp[$ip] ?? null);
                                    $category = (string) ($reviewRow['category'] ?? 'manual');
                                    $expiresAt = (string) ($reviewRow['expires_at'] ?? '');
                                ?>
                                <tr>
                                    <td><input class="ip-select" type="checkbox" name="selected_ips[]" value="<?= e($ip) ?>" form="reviewBulkForm" onchange="updateSelectedCount()" <?= isLoggedIn() ? '' : 'disabled' ?>></td>
                                    <td><?= (($reviewPage - 1) * $rowsPerPage) + $offset + 1 ?></td>
                                    <td class="ip"><span class="ip-chip"><?= e($ip) ?></span></td>
                                    <td>
                                        <span class="badge <?= e(ipReviewReasonBadgeClass((string) ($reviewRow['review_code'] ?? ''))) ?>"><?= e($reviewRow['review_label'] ?? 'مراجعة') ?></span>
                                        <div class="small-meta"><?= e($reviewRow['review_detail'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= e(ipCategoryBadgeClass($category)) ?>"><?= e(ipCategoryLabel($category)) ?></span>
                                        <?php if (!empty($reviewRow['note'])): ?>
                                            <div class="small-meta"><?= e($reviewRow['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= e(expirationBadgeClass($expiresAt)) ?>"><?= e(expirationLabel($expiresAt)) ?></span></td>
                                    <td>
                                        <?php if ($vtRow): ?>
                                            <span class="badge <?= e(vtBadgeClass((string) ($vtRow['vt_status'] ?? ''))) ?>"><?= e($vtRow['vt_status'] ?? 'غير معروف') ?></span>
                                            <div class="small-meta">Score: <?= e((string) ($vtRow['vt_malicious'] ?? 0)) ?> / <?= e((string) ($vtRow['vt_total'] ?? 0)) ?></div>
                                        <?php else: ?>
                                            <span class="badge badge-vt-muted">لم يتم الفحص</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($reviewRow['country'] ?? 'Unknown') ?></td>
                                    <td>
                                        <?= e($reviewRow['user'] ?? '-') ?>
                                        <?php if (!empty($reviewRow['added_at'])): ?>
                                            <div class="small-meta ip"><?= e($reviewRow['added_at']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" aria-label="تقسيم صفحات المراجعة">
                    <div class="pagination-info">
                        عرض <?= $reviewTotalRows === 0 ? 0 : (($reviewPage - 1) * $rowsPerPage) + 1 ?> - <?= min($reviewPage * $rowsPerPage, $reviewTotalRows) ?> من <?= number_format($reviewTotalRows) ?> IP
                    </div>
                    <div class="pagination-links">
                        <a class="page-link <?= $reviewPage <= 1 ? 'disabled' : '' ?>" href="<?= e(pageUrl('review_page', $reviewPage - 1)) ?>">السابق</a>
                        <?php for ($p = max(1, $reviewPage - 2); $p <= min($reviewTotalPages, $reviewPage + 2); $p++): ?>
                            <a class="page-link <?= $p === $reviewPage ? 'active' : '' ?>" href="<?= e(pageUrl('review_page', $p)) ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?= $reviewPage >= $reviewTotalPages ? 'disabled' : '' ?>" href="<?= e(pageUrl('review_page', $reviewPage + 1)) ?>">التالي</a>
                    </div>
                </div>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($currentPage === 'settings' && canManageUsers($users)): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="card-head">
                    <div>
                        <h2>إعدادات VirusTotal API</h2>
                        <p>يمكن للمدير إضافة أو تغيير مفتاح VirusTotal من هنا. لا يتم عرض المفتاح كاملاً بعد الحفظ.</p>
                    </div>
                    <span class="kbd"><?= $vtApiKey === '' ? 'غير مفعل' : e((string) ($vtConfig['source_label'] ?? 'مفعل')) ?></span>
                </div>

                <div class="stats" style="margin-bottom: 18px;">
                    <div class="stat-panel">
                        <div class="stat-label">حالة المفتاح</div>
                        <div class="stat-value" style="font-size: 20px;"><?= $vtApiKey === '' ? 'غير مضبوط' : e((string) ($vtConfig['masked'] ?? 'مخفي')) ?></div>
                        <div class="stat-help">المصدر: <?= e((string) ($vtConfig['source_label'] ?? 'غير مضبوط')) ?></div>
                    </div>
                    <div class="stat-panel">
                        <div class="stat-label">آخر تعديل</div>
                        <div class="stat-value" style="font-size: 20px;"><?= e((string) (($vtConfig['settings']['updated_at'] ?? '') ?: '-')) ?></div>
                        <div class="stat-help">بواسطة: <?= e((string) (($vtConfig['settings']['updated_by'] ?? '') ?: '-')) ?></div>
                    </div>
                    <div class="stat-panel">
                        <div class="stat-label">استهلاك اليوم</div>
                        <div class="stat-value" style="font-size: 20px;"><?= number_format((int) ($vtQuotaSnapshot['daily_count'] ?? 0)) ?> / <?= number_format((int) ($vtQuotaSnapshot['daily_quota'] ?? 500)) ?></div>
                        <div class="stat-help">انتظار الطلب التالي: <?= e(secondsToHumanArabic((int) ($vtQuotaSnapshot['wait_seconds'] ?? 0))) ?></div>
                    </div>
                </div>

                <form method="post" class="user-create-grid" autocomplete="off">
                    <?= csrfField() ?>
                    <input type="hidden" name="vt_settings_action" value="save">
                    <div class="form-group" style="margin-bottom: 0; grid-column: span 4;">
                        <label for="vt_api_key">مفتاح VirusTotal API الجديد</label>
                        <input id="vt_api_key" name="vt_api_key" type="password" placeholder="الصق المفتاح الجديد هنا" minlength="32" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                        <button class="btn" type="submit"><?= iconSvg('save') ?> حفظ / تحديث المفتاح</button>
                    </div>
                </form>

                <form method="post" class="inline-form" style="margin-top: 12px;" onsubmit="return confirm('هل تريد حذف مفتاح VirusTotal المحفوظ من لوحة المدير؟');">
                    <?= csrfField() ?>
                    <input type="hidden" name="vt_settings_action" value="clear">
                    <button class="btn btn-danger" type="submit" <?= (bool) ($vtConfig['has_saved_key'] ?? false) ? '' : 'disabled' ?>><?= iconSvg('trash') ?> حذف المفتاح المحفوظ</button>
                    <span class="small-meta" style="margin-right: 10px;">إذا كان VT_API_KEY مضبوطاً كمتغير بيئة، سيبقى مستخدماً بعد حذف المفتاح المحفوظ.</span>
                </form>

                <p class="note">لحماية المفتاح، يتم حفظ إعدادات VirusTotal داخل جدول <strong>app_state</strong> في SQLite. ملفات JSON القديمة تبقى للترحيل فقط ولا يعتمد عليها النظام بعد اكتمال النقل.</p>
            </div>
        </section>

        <section class="grid">
            <div class="card span-12">
                <div class="card-head">
                    <div>
                        <h2>SQLite والنسخ الاحتياطي</h2>
                        <p>إنشاء نسخة احتياطية من قاعدة SQLite وملف ips.txt، أو استعادة نسخة محفوظة من مجلد backups.</p>
                    </div>
                    <span class="kbd">schema v<?= number_format((int) ($schemaVersion['version'] ?? 0)) ?></span>
                </div>

                <div class="stats" style="margin-bottom: 18px;">
                    <div class="stat-panel">
                        <div class="stat-label">Schema version</div>
                        <div class="stat-value" style="font-size: 20px;"><?= number_format((int) ($schemaVersion['version'] ?? 0)) ?></div>
                        <div class="stat-help"><?= e((string) ($schemaVersion['migration'] ?? '')) ?></div>
                    </div>
                    <div class="stat-panel">
                        <div class="stat-label">مجلد النسخ</div>
                        <div class="stat-value" style="font-size: 20px; direction: ltr; text-align: left;"><?= e($backupDir) ?></div>
                        <div class="stat-help">الاحتفاظ: <?= number_format($backupRetentionDays) ?> يوم</div>
                    </div>
                    <div class="stat-panel">
                        <div class="stat-label">النسخ المعروضة</div>
                        <div class="stat-value" style="font-size: 20px;"><?= number_format(count($backupManifests)) ?></div>
                        <div class="stat-help">آخر 10 ملفات manifest</div>
                    </div>
                </div>

                <form method="post" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="backup_action" value="create">
                    <button class="btn" type="submit"><?= iconSvg('download') ?> إنشاء نسخة الآن</button>
                </form>

                <div class="table-wrap" style="margin-top: 16px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Manifest</th>
                                <th>النوع</th>
                                <th>تاريخ الإنشاء</th>
                                <th>Schema</th>
                                <th>حجم SQLite</th>
                                <th>حجم ips.txt</th>
                                <th>استعادة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backupManifests)): ?>
                                <tr>
                                    <td colspan="7"><div class="empty-state">لا توجد نسخ احتياطية بعد.</div></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($backupManifests as $backupRow): ?>
                                <tr>
                                    <td class="ip"><?= e($backupRow['manifest'] ?? '') ?></td>
                                    <td><?= e($backupRow['type'] ?? 'manual') ?></td>
                                    <td class="ip"><?= e($backupRow['created_at'] ?? '') ?></td>
                                    <td><?= number_format((int) ($backupRow['schema_version'] ?? 0)) ?></td>
                                    <td><?= number_format((int) ($backupRow['database_size'] ?? 0)) ?> bytes</td>
                                    <td><?= number_format((int) ($backupRow['feed_size'] ?? 0)) ?> bytes</td>
                                    <td>
                                        <form method="post" class="inline-form" onsubmit="return confirm('سيتم استبدال قاعدة SQLite و ips.txt بالنسخة المحددة. هل تريد المتابعة؟');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="backup_action" value="restore">
                                            <input type="hidden" name="backup_manifest" value="<?= e($backupRow['manifest'] ?? '') ?>">
                                            <button class="btn btn-danger" type="submit"><?= iconSvg('warning') ?> استعادة</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="note">قبل أي استعادة، ينشئ النظام نسخة <strong>pre_restore</strong> تلقائياً حتى يمكن الرجوع لحالة ما قبل الاستعادة.</p>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($currentPage === 'users' && canManageUsers($users)): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="card-head">
                    <div>
                        <h2>إدارة المستخدمين</h2>
                        <p>إضافة مستخدمين وتحديد الصلاحيات: مدير، مشغّل، أو مشاهدة فقط. يتم حفظ الحسابات في قاعدة <strong>SQLite</strong>.</p>
                    </div>
                    <span class="kbd"><?= number_format(count($users)) ?> حساب</span>
                </div>

                <form method="post" class="user-create-grid" autocomplete="off">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_action" value="create">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="new_username">اسم المستخدم</label>
                        <input id="new_username" name="new_username" type="text" placeholder="مثال: analyst1" pattern="[A-Za-z0-9_.-]{3,32}" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="new_display_name">الاسم الظاهر</label>
                        <input id="new_display_name" name="new_display_name" type="text" placeholder="اختياري">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="new_role">الصلاحية</label>
                        <select id="new_role" name="new_role">
                            <option value="operator">مشغّل</option>
                            <option value="viewer">مشاهدة فقط</option>
                            <option value="admin">مدير</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="new_password">كلمة المرور</label>
                        <input id="new_password" name="new_password" type="password" minlength="8" placeholder="8 أحرف على الأقل" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="new_password_confirm">تأكيد كلمة المرور</label>
                        <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" placeholder="أعد كتابتها" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <button class="btn" type="submit"><?= iconSvg('add') ?> إضافة مستخدم</button>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الاسم الظاهر</th>
                                <th>الصلاحية</th>
                                <th>الحالة</th>
                                <th>كلمة مرور جديدة</th>
                                <th>آخر دخول</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $username => $user): ?>
                                <?php
                                    $updateFormId = domId('updateUser', $username);
                                    $deleteFormId = domId('deleteUser', $username);
                                    $isCurrentUser = normalizeUsername((string) ($_SESSION['user'] ?? '')) === $username;
                                ?>
                                <tr>
                                    <td class="user-name">
                                        <?= e($username) ?>
                                        <?php if ($isCurrentUser): ?>
                                            <div class="small-meta">الحساب الحالي</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><input form="<?= e($updateFormId) ?>" name="display_name" type="text" value="<?= e($user['display_name'] ?? $username) ?>"></td>
                                    <td>
                                        <select form="<?= e($updateFormId) ?>" name="role">
                                            <?php foreach (['admin', 'operator', 'viewer'] as $roleOption): ?>
                                                <option value="<?= e($roleOption) ?>" <?= ($user['role'] ?? '') === $roleOption ? 'selected' : '' ?>><?= e(roleLabel($roleOption)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="role-meta"><?= e(roleDescription((string) ($user['role'] ?? 'viewer'))) ?></div>
                                    </td>
                                    <td>
                                        <select form="<?= e($updateFormId) ?>" name="active" <?= $isCurrentUser ? 'disabled' : '' ?>>
                                            <option value="1" <?= (bool) ($user['active'] ?? false) ? 'selected' : '' ?>>مفعل</option>
                                            <option value="0" <?= !(bool) ($user['active'] ?? false) ? 'selected' : '' ?>>معطل</option>
                                        </select>
                                        <?php if ($isCurrentUser): ?>
                                            <input form="<?= e($updateFormId) ?>" type="hidden" name="active" value="1">
                                        <?php endif; ?>
                                        <div style="margin-top: 8px;">
                                            <span class="badge <?= (bool) ($user['active'] ?? false) ? 'status-active' : 'status-disabled' ?>"><?= (bool) ($user['active'] ?? false) ? 'مفعل' : 'معطل' ?></span>
                                        </div>
                                    </td>
                                    <td><input form="<?= e($updateFormId) ?>" name="password" type="password" minlength="8" placeholder="اتركه فارغاً بدون تغيير"></td>
                                    <td class="ip"><?= e($user['last_login'] ?? '-') ?></td>
                                    <td>
                                        <div class="mini-actions">
                                            <form id="<?= e($updateFormId) ?>" method="post" class="inline-form">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="user_action" value="update">
                                                <input type="hidden" name="target_user" value="<?= e($username) ?>">
                                                <button class="btn btn-secondary" type="submit"><?= iconSvg('save') ?> حفظ</button>
                                            </form>
                                            <form id="<?= e($deleteFormId) ?>" method="post" class="inline-form" onsubmit="return confirm('هل تريد حذف هذا المستخدم؟');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="user_action" value="delete">
                                                <input type="hidden" name="target_user" value="<?= e($username) ?>">
                                                <button class="btn btn-danger" type="submit" <?= $isCurrentUser ? 'disabled' : '' ?>><?= iconSvg('trash') ?> حذف</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="note">لن تسمح الصفحة بحذف أو تعطيل آخر مدير مفعل. المستخدم بصلاحية <strong>مشاهدة فقط</strong> يستطيع عرض البيانات دون تعديل القائمة أو فحص VirusTotal.</p>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($currentPage === 'logs' && canManageUsers($users)): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="card-head">
                    <div>
                        <h2>سجل الدخول</h2>
                        <p>آخر محاولات الدخول الناجحة والفاشلة، مع عنوان المصدر وسبب الرفض عند توفره.</p>
                    </div>
                    <span class="kbd"><?= number_format(count($recentLoginEvents)) ?> حدث</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الحالة</th>
                                <th>المستخدم</th>
                                <th>IP المصدر</th>
                                <th>السبب</th>
                                <th>الوقت</th>
                                <th>User-Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLoginEvents)): ?>
                                <tr>
                                    <td colspan="6"><div class="empty-state">لم يتم تسجيل أحداث دخول بعد.</div></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($recentLoginEvents as $event): ?>
                                <tr>
                                    <td>
                                        <?php if ((int) ($event['success'] ?? 0) === 1): ?>
                                            <span class="badge badge-vt-success">نجاح</span>
                                        <?php else: ?>
                                            <span class="badge badge-vt-danger">فشل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($event['username'] ?? '') ?></td>
                                    <td class="ip"><?= e($event['source_ip'] ?? '') ?></td>
                                    <td><?= e($event['reason'] ?? '') ?></td>
                                    <td class="ip"><?= e($event['created_at'] ?? '') ?></td>
                                    <td><?= e($event['user_agent'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (in_array($currentPage, ['settings', 'users'], true) && !canManageUsers($users)): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="empty-state">هذه الصفحة مخصصة للمدير فقط.</div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($currentPage === 'health'): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="card-head">
                    <div>
                        <h2>صحة النظام</h2>
                        <p>فحص سريع لصلاحيات الملفات، SQLite، وحالة VirusTotal والطابور.</p>
                    </div>
                    <span class="kbd">OK <?= number_format((int) ($systemHealthSummary['ok'] ?? 0)) ?> · تنبيه <?= number_format((int) ($systemHealthSummary['warning'] ?? 0)) ?> · خطأ <?= number_format((int) ($systemHealthSummary['error'] ?? 0)) ?></span>
                </div>

                <div class="health-list">
                    <?php foreach ($systemHealthChecks as $check): ?>
                        <?php $status = (string) ($check['status'] ?? 'unknown'); ?>
                        <div class="health-item">
                            <div class="health-top">
                                <div>
                                    <div class="health-group"><?= e($check['group'] ?? '') ?></div>
                                    <div class="health-name"><?= e($check['name'] ?? '') ?></div>
                                </div>
                                <span class="badge <?= e(healthBadgeClass($status)) ?>"><?= e(healthStatusLabel($status)) ?></span>
                            </div>
                            <div class="health-detail"><?= e($check['detail'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($currentPage === 'logs'): ?>
        <section class="grid">
            <div class="card span-12">
                <div class="toolbar">
                    <div class="card-head" style="margin-bottom: 0;">
                        <div>
                            <h2>سجل العمليات</h2>
                            <p>يعرض آخر <?= number_format($maxLogRowsOnScreen) ?> عملية، مقسمة إلى <?= number_format($rowsPerPage) ?> سجلات في كل صفحة.</p>
                        </div>
                    </div>

                    <div class="search-box">
                        <span><?= iconSvg('search') ?></span>
                        <input type="search" id="logSearch" placeholder="بحث داخل السجل في الصفحة الحالية..." oninput="filterTable('logSearch', 'logTable')">
                    </div>
                </div>

                <div class="table-wrap">
                    <table id="logTable">
                        <thead>
                            <tr>
                                <th>العملية</th>
                                <th>IP</th>
                                <th>VirusTotal</th>
                                <th>VT Score</th>
                                <th>ASN / AS Owner</th>
                                <th>الدولة</th>
                                <th>المدينة</th>
                                <th>ISP</th>
                                <th>السبب</th>
                                <th>المستخدم</th>
                                <th>الوقت</th>
                                <th>IP المستخدم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log)): ?>
                                <tr>
                                    <td colspan="12">
                                        <div class="empty-state">لا توجد سجلات حتى الآن.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($pagedLog as $row): ?>
                                <tr>
                                    <td>
                                        <?php if (in_array((string) ($row['action'] ?? 'add'), ['delete', 'bulk_delete'], true)): ?>
                                            <span class="badge badge-delete">حذف</span>
                                        <?php elseif (($row['action'] ?? 'add') === 'vt_check'): ?>
                                            <span class="badge badge-check">فحص VT</span>
                                        <?php elseif (($row['action'] ?? 'add') === 'vt_bulk_check'): ?>
                                            <span class="badge badge-check">فحص جماعي VT</span>
                                        <?php elseif (($row['action'] ?? '') === 'metadata_update'): ?>
                                            <span class="badge badge-user">تصنيف IP</span>
                                        <?php elseif (str_starts_with((string) ($row['action'] ?? ''), 'user_')): ?>
                                            <span class="badge badge-check">إدارة مستخدم</span>
                                        <?php elseif (str_starts_with((string) ($row['action'] ?? ''), 'vt_key_')): ?>
                                            <span class="badge badge-check">إعداد VT</span>
                                        <?php else: ?>
                                            <span class="badge badge-add">إضافة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ip"><?= e($row['ip'] ?? '') ?></td>
                                    <td>
                                        <span class="badge <?= e(vtBadgeClass((string) ($row['vt_status'] ?? ''))) ?>"><?= e($row['vt_status'] ?? '-') ?></span>
                                        <?php if (!empty($row['vt_error'])): ?>
                                            <div class="small-meta"><?= e($row['vt_error']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ip">
                                        <?php if (!empty($row['vt_link'])): ?>
                                            <a href="<?= e($row['vt_link']) ?>" target="_blank" rel="noopener"><?= e(($row['vt_malicious'] ?? 0) . ' / ' . ($row['vt_total'] ?? 0)) ?></a>
                                            <div class="small-meta">Suspicious: <?= e($row['vt_suspicious'] ?? 0) ?> | Reputation: <?= e($row['vt_reputation'] ?? 0) ?></div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="as-cell"><?= e(vtAsText($row)) ?></td>
                                    <td><?= e($row['country'] ?? 'Unknown') ?></td>
                                    <td><?= e($row['city'] ?? 'Unknown') ?></td>
                                    <td><?= e($row['isp'] ?? 'Unknown') ?></td>
                                    <td><?= e($row['reason'] ?? '') ?></td>
                                    <td><?= e($row['user'] ?? '') ?></td>
                                    <td class="ip"><?= e($row['time'] ?? ($row['added_at'] ?? '')) ?></td>
                                    <td class="ip"><?= e($row['source_ip'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" aria-label="تقسيم صفحات السجل">
                    <div class="pagination-info">
                        عرض <?= $logTotalRows === 0 ? 0 : (($logPage - 1) * $rowsPerPage) + 1 ?> - <?= min($logPage * $rowsPerPage, $logTotalRows) ?> من <?= number_format($logTotalRows) ?> سجل
                    </div>
                    <div class="pagination-links">
                        <a class="page-link <?= $logPage <= 1 ? 'disabled' : '' ?>" href="<?= e(pageUrl('log_page', $logPage - 1)) ?>">السابق</a>
                        <?php for ($p = max(1, $logPage - 2); $p <= min($logTotalPages, $logPage + 2); $p++): ?>
                            <a class="page-link <?= $p === $logPage ? 'active' : '' ?>" href="<?= e(pageUrl('log_page', $p)) ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?= $logPage >= $logTotalPages ? 'disabled' : '' ?>" href="<?= e(pageUrl('log_page', $logPage + 1)) ?>">التالي</a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>
<?php endif; ?>

<script>
    function enhanceResponsiveTables() {
        document.querySelectorAll('table').forEach((table) => {
            const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());

            table.querySelectorAll('tbody tr').forEach((row) => {
                Array.from(row.children).forEach((cell, index) => {
                    if (!cell.hasAttribute('data-label') && headers[index]) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', enhanceResponsiveTables);

    function filterTable(inputId, tableId) {
        const query = document.getElementById(inputId).value.toLowerCase().trim();
        const table = document.getElementById(tableId);
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }

    function setAllPagesScope(enabled) {
        const hidden = document.getElementById('selectAllIpsScope');

        if (hidden) {
            hidden.value = enabled ? '1' : '0';
        }
    }

    function updateSelectedCount() {
        const allPages = document.getElementById('selectAllAllIps');
        const allPagesSelected = Boolean(allPages && allPages.checked);
        const selectedVisible = document.querySelectorAll('.ip-select:checked').length;
        const countEl = document.getElementById('selectedIpCount');
        const selectAll = document.getElementById('selectAllIps');
        const checkboxes = document.querySelectorAll('.ip-select');

        if (countEl) {
            countEl.textContent = allPagesSelected ? (TOTAL_IPS + ' / كل النتائج المفلترة') : selectedVisible.toString();
        }

        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && selectedVisible === checkboxes.length;
            selectAll.indeterminate = selectedVisible > 0 && selectedVisible < checkboxes.length && !allPagesSelected;
        }

        setAllPagesScope(allPagesSelected);
    }

    function toggleIpSelection(source) {
        const allPages = document.getElementById('selectAllAllIps');

        if (allPages) {
            allPages.checked = false;
        }

        setAllPagesScope(false);

        document.querySelectorAll('.ip-select').forEach((checkbox) => {
            checkbox.checked = source.checked;
        });

        updateSelectedCount();
    }

    function toggleAllPagesSelection(source) {
        setAllPagesScope(source.checked);

        document.querySelectorAll('.ip-select').forEach((checkbox) => {
            checkbox.checked = source.checked;
        });

        updateSelectedCount();
    }

    function handleSingleIpSelection() {
        const allPages = document.getElementById('selectAllAllIps');

        if (allPages && allPages.checked) {
            allPages.checked = false;
            setAllPagesScope(false);
        }

        updateSelectedCount();
    }

    function confirmBulkAction(event) {
        const submitter = event && event.submitter ? event.submitter : document.activeElement;
        const action = submitter && submitter.name ? submitter.name : '';
        const mode = submitter && submitter.value ? submitter.value : 'selected';
        const allPages = document.getElementById('selectAllAllIps');
        const allPagesSelected = Boolean(allPages && allPages.checked);
        const selected = document.querySelectorAll('.ip-select:checked').length;
        const allPagesOptionAvailable = Boolean(allPages);
        const targetText = (mode === 'all' || allPagesSelected)
            ? (IP_SEARCH_ACTIVE ? 'كل نتائج الفلاتر الحالية' : 'كل النتائج الحالية')
            : selected + ' IP';

        if (mode !== 'all' && !allPagesSelected && selected === 0) {
            alert(allPagesOptionAvailable ? 'اختر IP واحداً على الأقل، أو فعّل خيار تحديد كل النتائج المفلترة.' : 'اختر IP واحداً على الأقل.');
            return false;
        }

        if (action === 'bulk_check_vt') {
            return confirm('ستتم إضافة ' + targetText + ' إلى طابور VirusTotal، وقد يتم تطبيق حد أول ' + BULK_SCAN_LIMIT + ' IP في الطلب الواحد. هل تريد المتابعة؟');
        }

        if (action === 'bulk_delete_ips') {
            return confirm('سيتم حذف ' + targetText + ' من ips.txt. هل تريد المتابعة؟');
        }

        if (action === 'bulk_metadata_ips') {
            return confirm('سيتم تحديث التصنيف/تاريخ الانتهاء لـ ' + targetText + '. هل تريد المتابعة؟');
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.ip-select').forEach((checkbox) => {
            checkbox.addEventListener('change', handleSingleIpSelection);
        });

        updateSelectedCount();
    });

    const ADD_PROGRESS_THRESHOLD = <?= (int) $addProgressThreshold ?>;
    const ADD_PROGRESS_CHUNK_SIZE = <?= (int) $addProgressChunkSize ?>;
    const TOTAL_IPS = <?= (int) $ipTotalRows ?>;
    const BULK_SCAN_LIMIT = <?= (int) $bulkScanLimit ?>;
    const IP_SEARCH_ACTIVE = <?= count(array_filter($ipFilters, static fn ($value): bool => (string) $value !== '')) > 0 ? 'true' : 'false' ?>;
    const VT_QUEUE_ENABLED = <?= $currentPage === 'dashboard' && $vtApiKey !== '' && canCheckVirusTotal($users) ? 'true' : 'false' ?>;
    const VT_QUEUE_INITIAL_PENDING = <?= $currentPage === 'dashboard' && (((int) ($vtQueueStats['queued'] ?? 0)) + ((int) ($vtQueueStats['processing'] ?? 0))) > 0 ? 'true' : 'false' ?>;
    const VT_CSRF_TOKEN = <?= json_encode(ensureCsrfToken(), JSON_UNESCAPED_UNICODE) ?>;

    function isValidIpv4(ip) {
        const parts = ip.split('.');

        if (parts.length !== 4) {
            return false;
        }

        return parts.every((part) => {
            if (!/^\d+$/.test(part)) {
                return false;
            }

            const value = Number(part);
            return value >= 0 && value <= 255;
        });
    }

    function parseIpsForProgress(value) {
        const ips = value.split(/[\s,;]+/).map((ip) => ip.trim()).filter(Boolean).filter(isValidIpv4);
        return Array.from(new Set(ips));
    }

    function updateAddProgress(processed, total, added, skipped, invalid, message) {
        const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
        const panel = document.getElementById('addProgressPanel');
        const fill = document.getElementById('addProgressFill');
        const percentEl = document.getElementById('addProgressPercent');
        const details = document.getElementById('addProgressDetails');

        if (panel) {
            panel.hidden = false;
        }

        if (fill) {
            fill.style.width = Math.min(100, percent) + '%';
        }

        if (percentEl) {
            percentEl.textContent = Math.min(100, percent) + '%';
        }

        if (details) {
            details.textContent = message || ('تمت معالجة ' + processed + ' من ' + total + ' — تمت الإضافة: ' + added + '، مكرر/موجود: ' + skipped + '، غير صالح: ' + invalid);
        }
    }

    async function submitLargeAddWithProgress(event) {
        const form = document.getElementById('addIpsForm');

        if (!form || !window.fetch) {
            return true;
        }

        const ipsInput = document.getElementById('ips');
        const reasonInput = document.getElementById('reason');
        const categoryInput = document.getElementById('category');
        const expiresInput = document.getElementById('expires_at');
        const noteInput = document.getElementById('metadata_note');
        const checkVt = document.getElementById('check_virustotal');
        const submitBtn = document.getElementById('addIpsSubmit');
        const rawItems = ipsInput ? ipsInput.value.split(/[\s,;]+/).map((x) => x.trim()).filter(Boolean) : [];
        const validIps = parseIpsForProgress(ipsInput ? ipsInput.value : '');
        const invalidEstimate = Math.max(0, rawItems.length - validIps.length);

        if (validIps.length < ADD_PROGRESS_THRESHOLD) {
            return true;
        }

        event.preventDefault();

        const confirmed = confirm('سيتم إضافة ' + validIps.length + ' IP على دفعات مع شريط تقدم. هل تريد المتابعة؟');

        if (!confirmed) {
            return false;
        }

        const csrf = form.querySelector('input[name="csrf_token"]');
        const chunkSize = checkVt && checkVt.checked ? Math.max(1, Math.min(5, ADD_PROGRESS_CHUNK_SIZE)) : Math.max(1, ADD_PROGRESS_CHUNK_SIZE);
        let processed = 0;
        let added = 0;
        let skipped = 0;
        let invalid = invalidEstimate;

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        updateAddProgress(0, validIps.length, 0, 0, invalid, 'بدء الإضافة على دفعات...');

        try {
            for (let start = 0; start < validIps.length; start += chunkSize) {
                const chunk = validIps.slice(start, start + chunkSize);
                const body = new FormData();
                body.append('ajax_add_ips_chunk', '1');
                body.append('csrf_token', csrf ? csrf.value : '');
                body.append('ips', chunk.join('\n'));
                body.append('reason', reasonInput ? reasonInput.value : '');
                body.append('category', categoryInput ? categoryInput.value : 'manual');
                body.append('expires_at', expiresInput ? expiresInput.value : '');
                body.append('metadata_note', noteInput ? noteInput.value : '');

                if (checkVt && checkVt.checked && !checkVt.disabled) {
                    body.append('check_virustotal', '1');
                }

                const response = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (!data.ok) {
                    throw new Error(data.error || 'حدث خطأ أثناء الإضافة.');
                }

                processed += chunk.length;
                added += Number(data.added || 0);
                skipped += Number(data.skipped || 0);
                invalid += Number(data.invalid || 0);

                updateAddProgress(processed, validIps.length, added, skipped, invalid);
            }

            updateAddProgress(validIps.length, validIps.length, added, skipped, invalid, 'اكتملت الإضافة. تمت الإضافة: ' + added + '، مكرر/موجود: ' + skipped + '، غير صالح: ' + invalid + '. سيتم تحديث الصفحة الآن...');
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            updateAddProgress(processed, validIps.length, added, skipped, invalid, 'توقفت العملية: ' + error.message);

            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }

        return false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const addForm = document.getElementById('addIpsForm');

        if (addForm) {
            addForm.addEventListener('submit', submitLargeAddWithProgress);
        }
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function queueStatusLabel(status) {
        return {
            queued: 'منتظر',
            processing: 'جاري',
            completed: 'مكتمل',
            failed: 'فشل',
            skipped: 'متخطى'
        }[status] || 'غير معروف';
    }

    function queueBadgeClass(status) {
        return {
            completed: 'badge-vt-success',
            failed: 'badge-vt-danger',
            processing: 'badge-check'
        }[status] || 'badge-vt-muted';
    }

    function setQueueMetric(id, value) {
        const el = document.getElementById(id);

        if (el) {
            el.textContent = Number(value || 0).toLocaleString();
        }
    }

    function updateVirusTotalQueueUi(payload) {
        const stats = payload.stats || {};
        setQueueMetric('vtQueueQueued', stats.queued);
        setQueueMetric('vtQueueProcessing', stats.processing);
        setQueueMetric('vtQueueCompleted', stats.completed);
        setQueueMetric('vtQueueFailed', stats.failed);

        const rows = document.getElementById('vtQueueRows');
        if (rows && Array.isArray(payload.recent)) {
            if (payload.recent.length === 0) {
                rows.innerHTML = '<tr><td colspan="8"><div class="empty-state">لا توجد مهام VirusTotal في الطابور حالياً.</div></td></tr>';
            } else {
                rows.innerHTML = payload.recent.map((row) => {
                    const updatedAt = row.completed_at || row.started_at || row.created_at || '';
                    return '<tr>' +
                        '<td>' + escapeHtml(row.id) + '</td>' +
                        '<td class="ip">' + escapeHtml(row.ip) + '</td>' +
                        '<td><span class="badge ' + queueBadgeClass(row.status) + '">' + queueStatusLabel(row.status) + '</span></td>' +
                        '<td>' + escapeHtml(row.reason) + '</td>' +
                        '<td>' + escapeHtml(row.user) + '</td>' +
                        '<td>' + Number(row.attempts || 0).toLocaleString() + '</td>' +
                        '<td>' + escapeHtml(row.last_error) + '</td>' +
                        '<td class="ip">' + escapeHtml(updatedAt) + '</td>' +
                    '</tr>';
                }).join('');
            }

            enhanceResponsiveTables();
        }

        return Number(stats.queued || 0) + Number(stats.processing || 0);
    }

    async function refreshVirusTotalQueueStatus() {
        if (!VT_QUEUE_ENABLED || !window.fetch) {
            return 0;
        }

        const response = await fetch(window.location.pathname + '?ajax_vt_queue_status=1', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!data.ok) {
            throw new Error(data.error || 'تعذر تحديث حالة طابور VirusTotal.');
        }

        return updateVirusTotalQueueUi(data);
    }

    let vtQueueBusy = false;
    let vtQueueTimer = null;

    async function processVirusTotalQueueOnce() {
        if (!VT_QUEUE_ENABLED || vtQueueBusy || !window.fetch) {
            return;
        }

        vtQueueBusy = true;
        const btn = document.getElementById('vtQueueRunBtn');
        const message = document.getElementById('vtQueueMessage');

        if (btn) {
            btn.disabled = true;
        }

        try {
            const body = new FormData();
            body.append('ajax_vt_process_next', '1');
            body.append('csrf_token', VT_CSRF_TOKEN);

            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body,
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!data.ok) {
                throw new Error(data.error || 'تعذر معالجة طابور VirusTotal.');
            }

            if (message) {
                if (data.processed) {
                    message.textContent = 'تم فحص ' + data.ip + ' — الحالة: ' + data.status;
                } else if (data.deferred) {
                    message.textContent = 'تم تأجيل الفحص مؤقتاً: ' + (data.error || '');
                } else {
                    message.textContent = data.message || 'لا توجد مهام معلقة.';
                }
            }

            const active = await refreshVirusTotalQueueStatus();

            if (active > 0) {
                vtQueueTimer = window.setTimeout(processVirusTotalQueueOnce, 2200);
            }
        } catch (error) {
            if (message) {
                message.textContent = 'توقف عامل VirusTotal: ' + error.message;
            }
        } finally {
            vtQueueBusy = false;

            if (btn && VT_QUEUE_ENABLED) {
                btn.disabled = false;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (VT_QUEUE_ENABLED && VT_QUEUE_INITIAL_PENDING) {
            vtQueueTimer = window.setTimeout(processVirusTotalQueueOnce, 1200);
        }
    });

    function copyFeedLink() {
        const input = document.getElementById('feedLink');
        input.select();
        input.setSelectionRange(0, 99999);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value);
        } else {
            document.execCommand('copy');
        }
    }
</script>
</body>
</html>
