// assets/js/print-expenses.js
const PrintExpenses = (function () {
    const API_URL = 'api/expenses.php';

    // تنسيق العملة
    function formatCurrency(amount) {
        return Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }) + ' <small>ريال</small>';
    }

    async function printDay(date) {
        try {
            // 1. جلب البيانات
            const res = await fetch(`${API_URL}?action=list&date=${encodeURIComponent(date)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const result = await res.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'تعذر تحميل البيانات');
            }

            const expenses = result.data.expenses || [];
            const summary = result.data.summary || {};

            // 2. تجهيز النافذة
            const win = window.open('', '_blank', 'width=1000,height=1200');
            if (!win) {
                alert('فضلاً اسمح بالنوافذ المنبثقة (Pop-ups) لطباعة التقرير');
                return;
            }

            // 3. بناء صفوف الجدول
            const rowsHtml = expenses.length
                ? expenses.map((e, index) => `
              <tr>
                  <td>${index + 1}</td>
                  <td><strong>${e.category}</strong></td>
                  <td>${e.description || '-'}</td>
                  <td class="amount">${formatCurrency(e.amount)}</td>
                  <td><span class="badge">${e.expense_date.split(' ')[1] || e.expense_date}</span></td>
              </tr>
            `).join('')
                : `<tr><td colspan="5" class="empty-state">لا توجد مصروفات مسجلة لهذا اليوم</td></tr>`;

            // 4. بناء هيكل HTML المحسن
            const html = `
          <!DOCTYPE html>
          <html dir="rtl" lang="ar">
          <head>
              <meta charset="UTF-8">
              <title>تقرير مالي - ${summary.date || date}</title>
              <link rel="preconnect" href="https://fonts.googleapis.com">
              <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
              <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
              
              <style>
                  :root {
                      --primary: #333;
                      --accent: #d4a373; /* لون ذهبي ناعم مناسب لفساتين الزفاف */
                      --bg-light: #f9f9f9;
                      --border: #e0e0e0;
                  }
                  body {
                      font-family: 'Cairo', sans-serif;
                      background: #fff;
                      color: #333;
                      margin: 0;
                      padding: 40px;
                      -webkit-print-color-adjust: exact;
                  }
                  
                  /* ترويسة التقرير */
                  .header {
                      display: flex;
                      justify-content: space-between;
                      align-items: center;
                      margin-bottom: 40px;
                      border-bottom: 2px solid var(--primary);
                      padding-bottom: 20px;
                  }
                  .header h1 { margin: 0; font-size: 24px; color: var(--primary); }
                  .header p { margin: 5px 0 0; color: #666; font-size: 14px; }
                  .meta-data { text-align: left; }
  
                  /* بطاقات الملخص */
                  .summary-cards {
                      display: grid;
                      grid-template-columns: repeat(3, 1fr);
                      gap: 20px;
                      margin-bottom: 40px;
                  }
                  .card {
                      background: var(--bg-light);
                      padding: 20px;
                      border-radius: 12px;
                      border: 1px solid var(--border);
                      text-align: center;
                  }
                  .card h3 { margin: 0 0 10px; font-size: 14px; color: #777; }
                  .card .value { font-size: 22px; font-weight: 700; color: var(--primary); }
                  .card.income .value { color: #2ecc71; }
                  .card.expense .value { color: #e74c3c; }
                  
                  /* الجدول */
                  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                  th { 
                      background: var(--primary); 
                      color: #fff; 
                      padding: 12px 15px; 
                      font-weight: 600; 
                      font-size: 14px;
                      text-align: right;
                  }
                  td { 
                      padding: 12px 15px; 
                      border-bottom: 1px solid var(--border); 
                      font-size: 14px; 
                  }
                  tr:nth-child(even) { background-color: #fcfcfc; }
                  .amount { font-weight: 700; font-family: 'Tahoma', sans-serif; } /* للأرقام */
                  .badge { 
                      background: #eee; padding: 4px 8px; 
                      border-radius: 4px; font-size: 12px; 
                  }
                  .empty-state { text-align: center; padding: 30px; color: #999; }
  
                  /* التذييل والتوقيع */
                  .footer-section {
                      margin-top: 60px;
                      display: flex;
                      justify-content: space-between;
                  }
                  .signature-box {
                      width: 200px;
                      border-top: 1px solid #ccc;
                      text-align: center;
                      padding-top: 10px;
                      font-size: 14px;
                      color: #555;
                  }
                  .footer-note {
                      margin-top: 50px;
                      text-align: center;
                      font-size: 12px;
                      color: #aaa;
                  }
  
                  /* أزرار التحكم (لا تظهر عند الطباعة) */
                  .actions {
                      position: fixed; top: 20px; left: 20px;
                      background: #fff; padding: 10px;
                      border: 1px solid #ddd; border-radius: 8px;
                      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                  }
                  .btn {
                      padding: 8px 16px; border: none; border-radius: 4px;
                      cursor: pointer; font-family: 'Cairo', sans-serif;
                      font-weight: bold; margin-right: 5px;
                  }
                  .btn-print { background: var(--primary); color: #fff; }
                  .btn-close { background: #eee; color: #333; }
  
                  @media print {
                      .actions { display: none !important; }
                      body { padding: 0; }
                      .card { border: 1px solid #000; } /* لضمان وضوح الحدود */
                  }
              </style>
          </head>
          <body>
              <div class="actions">
                  <button class="btn btn-print" onclick="window.print()">طباعة التقرير</button>
                  <button class="btn btn-close" onclick="window.close()">إغلاق</button>
              </div>
  
              <div class="header">
                  <div>
                      <h1>تقرير حركة الصندوق اليومي</h1>
                      <p>نظام إدارة فساتين الزفاف</p>
                  </div>
                  <div class="meta-data">
                      <p><strong>التاريخ:</strong> ${summary.date || date}</p>
                      <p><strong>وقت الطباعة:</strong> ${new Date().toLocaleTimeString('en-GB')}</p>
                  </div>
              </div>
  
              <div class="summary-cards">
                  <div class="card income">
                      <h3>إجمالي الإيرادات (العربون)</h3>
                      <div class="value">${formatCurrency(summary.total_income)}</div>
                  </div>
                  <div class="card expense">
                      <h3>إجمالي المصروفات</h3>
                      <div class="value">${formatCurrency(summary.total_expenses)}</div>
                  </div>
                  <div class="card">
                      <h3>صافي الدخل</h3>
                      <div class="value" style="color: ${summary.net_income >= 0 ? 'var(--primary)' : 'red'}">
                          ${formatCurrency(summary.net_income)}
                      </div>
                  </div>
              </div>
  
              <table>
                  <thead>
                      <tr>
                          <th width="5%">#</th>
                          <th width="20%">التصنيف</th>
                          <th width="40%">التفاصيل</th>
                          <th width="20%">المبلغ</th>
                          <th width="15%">الوقت</th>
                      </tr>
                  </thead>
                  <tbody>
                      ${rowsHtml}
                  </tbody>
              </table>
  
              <div class="footer-section">
                  <div class="signature-box">توقيع المحاسب</div>
                  <div class="signature-box">ختم المحل</div>
              </div>
  
              <div class="footer-note">
                  تم استخراج هذا التقرير آلياً - ${new Date().toLocaleDateString('en-GB')}
              </div>
  
              <script>
                  // طباعة تلقائية بعد التحميل بمدة قصيرة لضمان ظهور الخطوط
                  setTimeout(() => {
                    // window.print(); // يمكنك تفعيل هذا السطر لو أردت الطباعة مباشرة
                  }, 500);
              </script>
          </body>
          </html>
        `;

            win.document.write(html);
            win.document.close();
            win.focus();

        } catch (err) {
            console.error('Error printing expenses:', err);
            alert('حدث خطأ أثناء التجهيز للطباعة: ' + err.message);
        }
    }

    return { printDay };
})();