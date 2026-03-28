<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ $survey->title }}</title>
    
    <!-- Custom Color Style -->
    <style>
    :root {
        --survey-color: {{ $surveyColor }};
    }
    .survey-accent-bg {
        background-color: var(--survey-color) !important;
    }
    .survey-accent-text {
        color: var(--survey-color) !important;
    }
    .survey-accent-border {
        border-color: var(--survey-color) !important;
    }
    
    /* Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 14px;
        line-height: 1.5;
        color: #374151;
        background-color: #ffffff;
        padding: 20px;
    }
    
    /* Form Styles */
    .survey-form {
        max-width: 100%;
    }
    
    .question-block {
        margin-bottom: 24px;
    }
    
    .question-label {
        display: block;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #111827;
    }
    
    .required {
        color: #dc2626;
    }
    
    /* Input Styles */
    input[type="text"],
    textarea,
    select {
        width: 100%;
        padding: 10px 12px;
        font-size: 14px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #f9fafb;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    input[type="text"]:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: var(--survey-color);
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    /* Radio and Checkbox */
    .option-group {
        margin-bottom: 8px;
    }
    
    .option-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding: 8px;
        border-radius: 6px;
        transition: background-color 0.15s ease-in-out;
    }
    
    .option-label:hover {
        background-color: #f3f4f6;
    }
    
    input[type="radio"],
    input[type="checkbox"] {
        margin-right: 8px;
        cursor: pointer;
    }
    
    /* Rating Stars */
    .rating-container {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    
    .star-button {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        transition: transform 0.15s ease-in-out;
    }
    
    .star-button:hover {
        transform: scale(1.1);
    }
    
    .star-icon {
        width: 32px;
        height: 32px;
        transition: color 0.15s ease-in-out;
    }
    
    .star-icon.filled {
        color: #fbbf24;
    }
    
    .star-icon.empty {
        color: #d1d5db;
    }
    
    .rating-text {
        margin-left: 8px;
        font-size: 14px;
        color: #6b7280;
    }
    
    /* NPS Buttons */
    .nps-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .nps-button {
        width: 40px;
        height: 40px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.15s ease-in-out;
    }
    
    .nps-button:hover {
        background-color: #f3f4f6;
    }
    
    .nps-button.selected {
        color: #ffffff;
        border-color: transparent;
    }
    
    .nps-button.selected.promoter {
        background-color: #10b981;
    }
    
    .nps-button.selected.passive {
        background-color: #f59e0b;
    }
    
    .nps-button.selected.detractor {
        background-color: #ef4444;
    }
    
    .nps-labels {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #6b7280;
    }
    
    /* Submit Button */
    .submit-button {
        width: 100%;
        padding: 12px 24px;
        font-size: 16px;
        font-weight: 600;
        color: #ffffff;
        background-color: var(--survey-color);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: opacity 0.15s ease-in-out;
        margin-top: 24px;
    }
    
    .submit-button:hover:not(:disabled) {
        opacity: 0.9;
    }
    
    .submit-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Success Message */
    .success-message {
        text-align: center;
        padding: 40px 20px;
    }
    
    .success-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        color: #10b981;
    }
    
    .success-title {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
    }
    
    .success-text {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 24px;
    }
    
    .continue-button {
        display: inline-flex;
        align-items: center;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #ffffff;
        background-color: var(--survey-color);
        border: none;
        border-radius: 6px;
        text-decoration: none;
        cursor: pointer;
        transition: opacity 0.15s ease-in-out;
    }
    
    .continue-button:hover {
        opacity: 0.9;
    }
    
    /* Loading State */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        body {
            padding: 12px;
        }
        
        .question-label {
            font-size: 15px;
        }
        
        .nps-button {
            width: 36px;
            height: 36px;
            font-size: 13px;
        }
    }
    </style>
    
    @vite('resources/assets/sass/app/app.scss')
</head>
<body>
    <div id="app">
        <survey :survey='@json($survey)' survey-color="{{ $surveyColor }}"></survey>
    </div>
    
    @vite('resources/assets/js/embed.js')
</body>
</html>

