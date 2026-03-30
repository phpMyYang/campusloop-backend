<style>
    @media print {
        /* LONG BOND PAPER (FOLIO) 8.5 x 13 inches */
        @page { 
            size: 8.5in 13in; 
            margin: 0.6in 0.75in; 
        }
        body { 
            -webkit-print-color-adjust: exact; print-color-adjust: exact; 
        }
    }
    
    body { 
        font-family: 'Georgia', 'Times New Roman', serif; 
        color: #1a1a1a; 
        line-height: 1.5; 
        font-size: 11.5pt; 
        margin: 0; 
    }
    
    /* PREMIUM LETTERHEAD */
    .letterhead 
    { 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        padding-bottom: 15px; 
        border-bottom: 3px double #2c3e50; 
        margin-bottom: 20px; 
    }
    .letterhead-logo 
    { 
        width: 75px; 
        height: auto; 
        margin-right: 20px; 
    }
    .letterhead-text 
    { 
        text-align: center; 
    }
    .school-name 
    { 
        font-size: 15pt; 
        font-weight: 600; 
        margin: 0; color: #2c3e50; 
        font-family: 'Arial', sans-serif; 
        letter-spacing: 0.5px; 
        text-transform: uppercase; 
    }
    .school-address 
    { 
        margin: 4px 0 0 0; 
        font-size: 9.5pt; 
        font-family: 'Arial', sans-serif; 
        color: #444; 
        text-transform: uppercase; 
    }
    .school-contact 
    { 
        margin: 2px 0 0 0; 
        font-size: 9pt; 
        font-family: 'Arial', sans-serif; 
        color: #555; 
        text-transform: uppercase; 
    }
    
    /* FORM TITLE */
    .form-title 
    { 
        text-align: center; 
        font-size: 15pt; 
        font-weight: bold; 
        text-transform: uppercase; 
        margin-top: 10px; 
        margin-bottom: 5px; 
        letter-spacing: 1.5px; 
        font-family: 'Arial', sans-serif; 
    }
    .form-instruction 
    { 
        text-align: center; 
        font-size: 10.5pt; 
        margin-bottom: 25px; 
        color: #000; 
    }
    
    /* PREMIUM INFO TABLE */
    .info-container 
    { 
        display: flex; 
        justify-content: space-between; 
        margin-bottom: 30px; 
        font-size: 11pt; 
        font-family: 'Arial', sans-serif; 
    }
    .info-col 
    { 
        width: 48%; 
    }
    .info-row 
    { 
        display: flex; 
        align-items: flex-end; 
        margin-bottom: 8px; 
    }
    .info-label 
    { 
        font-weight: bold; 
        margin-right: 10px; 
        color: #2c3e50; 
        white-space: nowrap; 
    }
    .info-value 
    { 
        flex-grow: 1; 
        border-bottom: 1px solid #000; 
        padding: 0 5px 2px 5px; 
        font-family: 'Georgia', serif; 
        font-size: 11.5pt; 
    }
    
    .score-box 
    { 
        border: 2px solid #2c3e50; 
        padding: 5px 15px; 
        text-align: center; 
        font-size: 14pt; 
        font-weight: bold; 
        border-radius: 4px; 
        background-color: #f8f9fa; 
    }

    /* SECTIONS */
    .section-block 
    { 
        margin-top: 30px; 
        margin-bottom: 15px; 
        padding: 5px 10px; 
        background-color: #f1f3f5; 
        page-break-after: avoid; 
    }
    .section-title 
    { 
        font-weight: bold; 
        font-size: 11.5pt; 
        text-transform: uppercase; 
        font-family: 'Arial', sans-serif; 
    }
    .section-instruction 
    { 
        font-style: italic; 
        font-size: 10.5pt; 
        margin-left: 5px; 
        color: #555; 
    }
    
    /* QUESTIONS */
    .q-container 
    { 
        margin-bottom: 18px; 
        page-break-inside: avoid; 
    }
    .q-header 
    { 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-start; 
        font-weight: bold; 
    }
    
    /* LAYOUT FIX PARA SA MAHONG TANONG AT POINTS */
    .q-text 
    { 
        flex-grow: 1; 
        padding-right: 15px; 
        text-align: justify; 
    }
    .q-points 
    { 
        flex-shrink: 0; 
        white-space: nowrap; 
        color: #198754;
    }

    .choices-grid 
    { 
        display: flex; 
        flex-wrap: wrap; 
        width: 100%; 
        margin-top: 6px; 
        padding-left: 20px; 
    }
    .choice-col 
    { 
        width: 50%; 
        padding: 4px 0; 
        font-size: 11pt; 
        box-sizing: border-box; 
    }
    .short-answer 
    { 
        margin-top: 5px; 
        font-size: 11pt; 
        padding-left: 20px; 
    }
    
    /* GRADING COLORS */
    .text-success 
    { 
        color: #198754; 
        font-weight: bold; 
    }
    .text-danger 
    { 
        color: #dc3545; 
        font-weight: bold; 
    }
    .correction-text 
    { 
        font-size: 9.5pt; 
        color: #198754; 
        font-style: italic; 
        margin-top: 4px; 
        padding-left: 20px; 
        border-left: 2px solid #198754; 
        margin-left: 20px; 
        padding-top: 2px; 
        padding-bottom: 2px; 
    }

    /* PRINT META FOOTER */
    .print-meta 
    { 
        margin-top: 50px; 
        font-size: 8.5pt; 
        font-family: 'Arial', sans-serif; 
        text-align: center; 
        color: #777; 
        border-top: 1px solid #ddd; 
        padding-top: 15px; 
    }
</style>