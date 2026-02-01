/**
 * Expenses Management Module - FIXED
 * Handles expense tracking and net income calculations
 */

const ExpensesManager = {
    currentDate: new Date().toISOString().split('T')[0],
    expensesData: [], // نخزن فيها المصروفات لسهولة التعديل
    
    init() {
        console.log('Initializing Expenses Manager...');
        this.setupEventListeners();
        this.loadExpenses();
        this.loadCategories();
    },
    
    setupEventListeners() {
        const addBtn = document.getElementById('add-expense-btn');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.showAddExpenseModal());
        }
        
        const saveForm = document.getElementById('expense-form');
        if (saveForm) {
            saveForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveExpense();
            });
        }
        
        const dateFilter = document.getElementById('expense-date-filter');
        if (dateFilter) {
            dateFilter.value = this.currentDate;
            dateFilter.addEventListener('change', (e) => {
                this.currentDate = e.target.value;
                this.loadExpenses();
            });
        }

        // زر طباعة مصروفات اليوم (يستدعي موديول مستقل)
        const printBtn = document.getElementById('print-expenses-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                if (typeof PrintExpenses !== 'undefined' && PrintExpenses.printDay) {
                    PrintExpenses.printDay(this.currentDate);
                } else {
                    alert('وحدة طباعة المصروفات غير محملة (PrintExpenses)');
                }
            });
        }

        // Connect the new "طباعة مصروفات اليوم" button (from sales actions)
        const printTodayBtn = document.getElementById('print-expenses-today-btn');
        if (printTodayBtn) {
            printTodayBtn.addEventListener('click', () => {
                if (typeof PrintExpenses !== 'undefined' && PrintExpenses.printDay) {
                    const today = new Date().toISOString().split('T')[0];
                    PrintExpenses.printDay(today);
                } else {
                    alert('وحدة طباعة المصروفات غير محملة (PrintExpenses)');
                }
            });
        }

        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.getAttribute('data-tab');
                if (targetTab === 'expenses-content') {
                    // Reload expenses when tab is opened
                    this.loadExpenses();
                }
            });
        });
    },
    
    async loadExpenses() {
        try {
            const response = await fetch(`api/expenses.php?action=list&date=${this.currentDate}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.status === 'success') {
                this.expensesData = result.data.expenses || [];
                this.renderExpenses(this.expensesData);
                this.updateSummary(result.data.summary);
            } else {
                this.showError('فشل تحميل المصروفات: ' + result.message);
            }
        } catch (error) {
            console.error('Error loading expenses:', error);
            this.showError('فشل تحميل المصروفات');
        }
    },
    
    renderExpenses(expenses) {
        const tbody = document.getElementById('expenses-table-body');
        if (!tbody) return;
        
        if (expenses.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">لا توجد مصروفات لهذا اليوم</td></tr>';
            return;
        }
        
        tbody.innerHTML = expenses.map(expense => `
            <tr>
                <td>${expense.expense_date}</td>
                <td>${expense.category}</td>
                <td>${this.formatCurrency(expense.amount)}</td>
                <td>${expense.description || '-'}</td>
                <td class="actions">
                    <button class="btn-action edit" onclick="ExpensesManager.editExpense(${expense.id})" title="تعديل">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-action delete" onclick="ExpensesManager.deleteExpense(${expense.id})" title="حذف">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    },

    // دالة التعديل: تعبّي بيانات المصروف في الفورم وتفتح المودال
    editExpense(id) {
        const expense = this.expensesData.find(e => Number(e.id) === Number(id));
        if (!expense) {
            alert('تعذر العثور على بيانات هذا المصروف');
            return;
        }

        const modal = document.getElementById('expense-modal');
        if (modal) {
            modal.style.display = 'block';
        }

        const dateInput = document.getElementById('expense-date');
        const amountInput = document.getElementById('expense-amount');
        const categorySelect = document.getElementById('expense-category');
        const descInput = document.getElementById('expense-description');
        const idInput = document.getElementById('expense-id'); // حقل مخفي في الفورم

        if (dateInput) dateInput.value = expense.expense_date;
        if (amountInput) amountInput.value = expense.amount;
        if (categorySelect) categorySelect.value = expense.category;
        if (descInput) descInput.value = expense.description || '';
        if (idInput) idInput.value = expense.id;
    },
    
    updateSummary(summary) {
        const updateElement = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = this.formatCurrency(value);
        };
        
        updateElement('total-income-today', summary.total_income);
        updateElement('total-expenses-today', summary.total_expenses);
        updateElement('net-income-today', summary.net_income);
        
        const netEl = document.getElementById('net-income-today');
        if (netEl) {
            netEl.style.color = summary.net_income >= 0 ? '#2ecc71' : '#e74c3c';
            netEl.style.fontWeight = 'bold';
        }
    },
    
    async loadCategories() {
        try {
            const response = await fetch('api/expenses.php?action=categories');
            const result = await response.json();
            
            if (result.status === 'success') {
                const select = document.getElementById('expense-category');
                if (select) {
                    select.innerHTML = '<option value="">اختر الفئة</option>' +
                        result.data.map(cat => `<option value="${cat}">${cat}</option>`).join('');
                }
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    },
    
    showAddExpenseModal() {
        const modal = document.getElementById('expense-modal');
        if (modal) {
            modal.style.display = 'block';
            const dateInput = document.getElementById('expense-date');
            if (dateInput) dateInput.value = this.currentDate;
        }

        // تفريغ id للتأكيد أن العملية "إضافة" وليست "تعديل"
        const idInput = document.getElementById('expense-id');
        if (idInput) {
            idInput.value = '';
        }
    },
    
    async saveExpense() {
        const idInput = document.getElementById('expense-id');
        const isEdit = idInput && idInput.value;

        const formData = {
            id: isEdit ? Number(idInput.value) : undefined,
            expense_date: document.getElementById('expense-date').value,
            amount: parseFloat(document.getElementById('expense-amount').value),
            category: document.getElementById('expense-category').value,
            description: document.getElementById('expense-description').value
        };
        
        if (!formData.category || !formData.amount) {
            alert('يرجى إدخال الفئة والمبلغ');
            return;
        }
        
        try {
            const response = await fetch(`api/expenses.php?action=${isEdit ? 'update' : 'create'}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                alert(isEdit ? '✅ تم تعديل المصروف بنجاح' : '✅ تم إضافة المصروف بنجاح');
                this.closeModal();
                const form = document.getElementById('expense-form');
                if (form) form.reset();
                await this.loadExpenses();
            } else {
                alert('❌ خطأ: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving expense:', error);
            alert('فشل حفظ المصروف');
        }
    },
    
    async deleteExpense(id) {
        if (!confirm('هل أنت متأكد من حذف هذا المصروف؟')) return;
        
        try {
            const response = await fetch('api/expenses.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                alert('تم الحذف بنجاح');
                await this.loadExpenses();
            }
        } catch (error) {
            console.error('Error deleting expense:', error);
            alert('فشل الحذف');
        }
    },
    
    closeModal() {
        const modal = document.getElementById('expense-modal');
        if (modal) modal.style.display = 'none';
    },
    
    formatCurrency(amount) {
        return Number(amount).toLocaleString('en-US') + ' ريال';
    },
    
    showError(message) {
        const tbody = document.getElementById('expenses-table-body');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">${message}</td></tr>`;
        }
        alert(message);
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('expenses-content')) {
        ExpensesManager.init();
    }
});
