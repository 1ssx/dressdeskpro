/**
 * ---------------------------------------------------------------------
 *  Daily Ledger Printing System – Professional Edition
 *  Author: Abdulfattah
 *  Description:
 *      A fully structured, modular and scalable daily report generator
 *      for sales, invoices and expenses. This system fetches data from 
 *      backend API, builds a well-styled printable document, and prints it
 *      through an isolated iFrame to guarantee clean output.
 * ---------------------------------------------------------------------
 */

class DailyLedgerPrinter {

    constructor(config) {
        this.button = document.getElementById(config.buttonId);
        this.apiURL = config.apiURL;

        if (this.button) {
            this.button.addEventListener("click", (e) => {
                e.preventDefault();
                this.generate();
            });
        }
    }



    /**
     * ----------------------------------------------------------
     *  Main Process Controller
     * ----------------------------------------------------------
     */
    async generate() {
        this._lockButton("جاري التحضير...");

        try {
            const data = await this._fetchReportData();
            // معاينة قبل الطباعة
            openPrintPreview(this._buildHTML(data));


        } catch (error) {
            this._showError("تعذر جلب تقرير الدفتر اليومي", error.message);
        }

        this._unlockButton();
    }

    /**
     * ----------------------------------------------------------
     *  API Fetch Layer
     * ----------------------------------------------------------
     */
    async _fetchReportData() {
        const response = await fetch(this.apiURL);

        if (!response.ok) {
            throw new Error("فشل الاتصال بالسيرفر");
        }

        const result = await response.json();

        if (result.status !== "success") {
            throw new Error(result.message ?? "خطأ غير معروف");
        }

        // API returns data wrapped in result.data, but buildHTML expects it directly
        // So we return the full result object for compatibility
        return result.data || result;
    }

    /**
     * ----------------------------------------------------------
     *  Arabic Operation Translation
     * ----------------------------------------------------------
     */
    translateOperation(type) {
        const map = {
            'sell': 'بيع',
            'sale': 'بيع',
            'rent': 'إيجار',
            'design': 'تصميم',
            'design-sell': 'تصميم وبيع',
            'design-sale': 'تصميم وبيع',
            'design-rent': 'تصميم وإيجار'
        };

        return map[type?.toLowerCase()] || type || "غير محدد";
    }

    /**
     * ----------------------------------------------------------
     *  Arabic Payment Status Translation
     * ----------------------------------------------------------
     */
    translatePaymentStatus(status) {
        const map = {
            'paid': 'سداد كامل',
            'partial': 'سداد جزئي',
            'unpaid': 'غير مدفوع'
        };

        return map[status?.toLowerCase()] || status || "غير محدد";
    }

    /**
     * ----------------------------------------------------------
     *  Printing Controller
     * ----------------------------------------------------------
     */
    _printDocument(data) {
        const documentHTML = this._buildHTML(data);

        const frame = document.createElement("iframe");
        frame.style.width = "0";
        frame.style.height = "0";
        frame.style.border = "0";
        frame.style.visibility = "hidden";

        document.body.appendChild(frame);

        const frameDoc = frame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(documentHTML);
        frameDoc.close();

        frame.onload = () => {
            frame.contentWindow.focus();
            frame.contentWindow.print();

            setTimeout(() => frame.remove(), 1500);
        };
    }

    /**
     * ----------------------------------------------------------
     *  HTML Builder – Clean, Elegant, Classy
     * ----------------------------------------------------------
     */
    _buildHTML(data) {

        const formatDate = new Date().toLocaleDateString("en-GB", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric"
        });

        const money = (v) =>
            parseFloat(v).toLocaleString("en-US", { minimumFractionDigits: 2 }) + " ريال";

        return `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>الدفتر اليومي - ${data.date}</title>

            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: 'Segoe UI', 'Arial', 'Tahoma', sans-serif; 
                    padding: 30px; 
                    color: #2c3e50; 
                    background: #f5f7fa;
                    direction: rtl;
                    text-align: right;
                }
                .report-box { 
                    max-width: 1000px; 
                    margin: 0 auto; 
                    padding: 40px; 
                    background: #ffffff;
                    border: 2px solid #e0e0e0; 
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }

                h1 { 
                    text-align: center; 
                    margin-bottom: 10px; 
                    color: #2c3e50;
                    font-size: 28px;
                    font-weight: 700;
                }
                .sub-header { 
                    text-align: center; 
                    color: #7f8c8d; 
                    margin-bottom: 30px; 
                    font-size: 16px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #ecf0f1;
                }

                .grid { 
                    display: grid; 
                    grid-template-columns: repeat(4, 1fr); 
                    gap: 20px; 
                    margin-bottom: 35px; 
                }
                .cell {
                    padding: 20px; 
                    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
                    border: 1px solid #e0e0e0; 
                    border-radius: 8px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    transition: transform 0.2s;
                }
                .cell .title { 
                    font-size: 14px; 
                    color: #7f8c8d; 
                    margin-bottom: 10px; 
                    font-weight: 500;
                }
                .cell .value { 
                    font-size: 20px; 
                    font-weight: 700; 
                    color: #2c3e50;
                }

                .section { 
                    margin-top: 35px; 
                    margin-bottom: 15px;
                    font-size: 18px; 
                    font-weight: 700; 
                    color: #2c3e50;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #3498db;
                }

                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 15px; 
                    font-size: 14px;
                    direction: rtl;
                    text-align: right;
                }
                table th, table td { 
                    border: 1px solid #ddd; 
                    padding: 12px 15px; 
                    text-align: right;
                }
                table th { 
                    background: #34495e;
                    color: #ffffff;
                    font-weight: 600;
                    text-align: center;
                }
                table td {
                    background: #ffffff;
                }
                table tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                table tbody tr:hover {
                    background: #e8f4f8;
                }

                .paid { color: #27ae60; font-weight: 700; }
                .partial { color: #f39c12; font-weight: 700; }
                .unpaid { color: #e74c3c; font-weight: 700; }

                .footer-note {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ecf0f1;
                    color: #95a5a6;
                    font-size: 12px;
                }

                @media print {
                    body { 
                        padding: 0; 
                        background: #ffffff;
                    }
                    .report-box { 
                        border: none; 
                        box-shadow: none;
                        padding: 20px;
                    }
                    .cell {
                        box-shadow: none;
                        border: 1px solid #ddd;
                    }
                    table tbody tr {
                        page-break-inside: avoid;
                    }
                    @page {
                        margin: 1.5cm;
                        size: A4;
                    }
                }
            </style>
        </head>

        <body>
            <div class="report-box">

                <h1>تقرير الدفتر اليومي</h1>
                <div class="sub-header">${formatDate} — ${data.date}</div>

                <!-- Summary Grid -->
                <div class="grid">
                    <div class="cell">
                        <div class="title">إجمالي المبيعات</div>
                        <div class="value">${money(data.summary.total_sales_value)}</div>
                    </div>
                    <div class="cell">
                        <div class="title">المقبوضات</div>
                        <div class="value">${money(data.summary.total_income)}</div>
                    </div>
                    <div class="cell">
                        <div class="title">المصروفات</div>
                        <div class="value" style="color:#c0392b">${money(data.summary.total_expenses)}</div>
                    </div>
                    <div class="cell">
                        <div class="title">صافي الدرج</div>
                        <div class="value" style="color:#27ae60">${money(data.summary.net_cash)}</div>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="section">حركة الفواتير والمقبوضات</div>

                <table>
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>نوع العملية</th>
                            <th>الحالة</th>
                            <th>الإجمالي</th>
                            <th>المدفوع اليوم</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.invoices.length
                ? data.invoices.map(inv => {
                    const statusLabel = this.translatePaymentStatus(inv.payment_status);
                    const statusClass = inv.payment_status === 'paid' ? 'paid' :
                        (inv.payment_status === 'partial' ? 'partial' : 'unpaid');
                    return `
                                <tr>
                                    <td>${inv.invoice_number}</td>
                                    <td>${this.translateOperation(inv.operation_type)}</td>
                                    <td class="${statusClass}">
                                        ${statusLabel}
                                    </td>
                                    <td>${money(inv.total_price)}</td>
                                    <td>${money(inv.deposit_amount)}</td>
                                </tr>`;
                }).join('')
                : `<tr><td colspan="5" style="text-align:center">لا توجد فواتير اليوم</td></tr>`
            }
                    </tbody>
                </table>

                <!-- Expenses -->
                <div class="section">المصروفات اليومية</div>
                <table>
                    <thead>
                        <tr>
                            <th>الفئة</th>
                            <th>الوصف</th>
                            <th>المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.expenses.length
                ? data.expenses.map(exp => `
                                <tr>
                                    <td>${exp.category}</td>
                                    <td>${exp.description || "-"}</td>
                                    <td style="color:#c0392b">${money(exp.amount)}</td>
                                </tr>`
                ).join('')
                : `<tr><td colspan="3" style="text-align:center">لا توجد مصروفات اليوم</td></tr>`
            }
                    </tbody>
                </table>

                <div class="footer-note">
                    تم استخراج التقرير آلياً بتاريخ ${new Date().toLocaleString("en-GB")}
                </div>
            </div>
        </body>
        </html>`;
    }

    /**
     * ----------------------------------------------------------
     *  Button UI Controller
     * ----------------------------------------------------------
     */
    _lockButton(txt) {
        if (!this.button) return;
        this.button.disabled = true;
        this.button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${txt}`;
    }

    _unlockButton() {
        if (!this.button) return;
        this.button.disabled = false;
        this.button.innerHTML = `<i class="fas fa-file-invoice"></i> الدفتر اليومي`;
    }

    /**
     * ----------------------------------------------------------
     *  Elegant Error Modal
     * ----------------------------------------------------------
     */
    _showError(title, message) {
        const modal = document.createElement("div");

        modal.innerHTML = `
            <div style="position:fixed;top:0;left:0;width:100%;height:100%;
                        background:rgba(0,0,0,.5);display:flex;align-items:center;
                        justify-content:center;z-index:9999;">
                <div style="background:white;padding:25px;border-radius:8px;
                            width:350px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,.2);">
                    <h3 style="color:#c0392b;margin-bottom:5px">${title}</h3>
                    <p style="margin-bottom:15px">${message}</p>
                    <button onclick="this.parentNode.parentNode.remove()" 
                            style="padding:8px 15px;background:#3498db;color:white;
                                   border:none;border-radius:5px;cursor:pointer;">
                        إغلاق
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }
}
/**
 * ----------------------------------------------------------
 * Preview Window Printer
 * ----------------------------------------------------------
 * Opens a clean preview window containing the report HTML
 * with a "Print" button at the top.
 */
function openPrintPreview(htmlContent) {
    const previewWindow = window.open("", "_blank", "width=900,height=900");

    previewWindow.document.open();
    previewWindow.document.write(`
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>معاينة التقرير</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    margin: 0; 
                    padding: 0; 
                    font-family: 'Segoe UI', 'Arial', 'Tahoma', sans-serif; 
                    background: #f5f7fa; 
                    direction: rtl;
                }
                #toolbar {
                    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
                    color: white;
                    padding: 15px 20px;
                    text-align: center;
                    font-size: 16px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 15px;
                }
                #toolbar button {
                    padding: 10px 20px;
                    background: #3498db;
                    border: none;
                    border-radius: 6px;
                    color: white;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s ease;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                #toolbar button:hover {
                    background: #2980b9;
                }
                #toolbar button:active {
                    transform: scale(0.98);
                }
                iframe {
                    width: 100%;
                    height: calc(100vh - 60px);
                    border: none;
                    background: white;
                    display: block;
                }
            </style>
        </head>
        <body>
            <div id="toolbar">
                <button onclick="document.getElementById('frame').contentWindow.print()">
                    طباعة التقرير
                </button>
            </div>

            <iframe id="frame"></iframe>

            <script>
                const doc = document.getElementById('frame').contentWindow.document;
                doc.open();
                doc.write(\`${htmlContent.replace(/`/g, "\\`")}\`);
                doc.close();
            <\/script>
        </body>
        </html>
    `);
    previewWindow.document.close();
}


/**
 * ----------------------------------------------------------
 *  Initialize the System
 * ----------------------------------------------------------
 */
document.addEventListener("DOMContentLoaded", () => {
    new DailyLedgerPrinter({
        buttonId: "daily-ledger-btn",
        apiURL: "api/daily_report.php"
    });
});
