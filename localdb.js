// Banco de dados local para contasLGS rodando no GitHub Pages
const db = {
    get: (key) => JSON.parse(localStorage.getItem('contasLGS_' + key) || '[]'),
    set: (key, data) => localStorage.setItem('contasLGS_' + key, JSON.stringify(data)),
    init: () => {
        if (!localStorage.getItem('contasLGS_users')) {
            db.set('users', []);
            db.set('transactions', []);
            db.set('links', []);
        }
        
        // Garante que exista pelo menos um usuário administrador padrão
        const users = db.get('users');
        if (!users.some(u => u.role === 'admin')) {
            users.push({
                id: 1,
                name: 'Administrador LGS',
                email: 'admin@lgs.com',
                username: 'admin',
                password: 'admin',
                role: 'admin',
                created_at: new Date().toISOString()
            });
            db.set('users', users);
        }
    }
};
db.init();

const app = {
    currentUser: JSON.parse(localStorage.getItem('contasLGS_currentUser') || 'null'),

    logoutUser: function() {
        localStorage.removeItem('contasLGS_currentUser');
        window.location.href = 'login.html';
    },

    checkAuth: function() {
        const isAuthPage = window.location.pathname.includes('login.html') || window.location.pathname.includes('cadastro.html');
        if (!this.currentUser && !isAuthPage) {
            window.location.href = 'login.html';
        } else if (this.currentUser && isAuthPage) {
            window.location.href = 'index.html';
        }
        
        if (this.currentUser && document.getElementById('displayUserName')) {
            document.getElementById('displayUserName').textContent = this.currentUser.name;
            document.getElementById('sidebarAvatar').textContent = this.currentUser.name.substring(0,2).toUpperCase();
            document.getElementById('displayUserRole').textContent = this.currentUser.role === 'admin' ? 'Administrador' : 'Usuário';
            
            if (this.currentUser.role === 'admin' && document.getElementById('navAdmin')) {
                document.getElementById('navAdmin').style.display = 'block';
            }
        }
    }
};

app.checkAuth();

// MOCK API FETCH
async function apiFetch(url, options = {}) {
    await new Promise(r => setTimeout(r, 150)); // Simula rede

    if (!app.currentUser) {
        window.location.href = 'login.html';
        return null;
    }

    const isApiPhp = url.startsWith('api.php');
    if (!isApiPhp) return { error: 'Not Found' };

    const searchParams = new URLSearchParams(url.split('?')[1] || '');
    const route = searchParams.get('route') || 'transactions';
    const method = options.method || 'GET';
    const body = options.body ? JSON.parse(options.body) : null;
    const uid = app.currentUser.id;

    if (route === 'transactions') {
        const id = searchParams.get('id');
        let txs = db.get('transactions').filter(t => t.user_id === uid);
        
        if (method === 'GET') {
            if (id) return { success: true, transactions: txs.filter(t => t.id == id) };
            
            // Stats
            let balance = 0, paid_income = 0, pending_income = 0, paid_expense = 0, pending_expense = 0;
            let cat_breakdown = {};
            
            txs.forEach(t => {
                const amt = parseFloat(t.amount);
                if (t.type === 'Receita') {
                    if (t.status === 'Pago') { paid_income += amt; balance += amt; }
                    else pending_income += amt;
                } else {
                    if (t.status === 'Pago') { paid_expense += amt; balance -= amt; }
                    else pending_expense += amt;
                    
                    if (t.status === 'Pago') {
                        cat_breakdown[t.category] = (cat_breakdown[t.category] || 0) + amt;
                    }
                }
            });
            
            return {
                success: true,
                transactions: txs.reverse(),
                stats: { balance, paid_income, pending_income, paid_expense, pending_expense, category_breakdown: cat_breakdown },
                categories: { Receita: ['Salário', 'Investimentos', 'Outros'], Despesa: ['Casa', 'Alimentação', 'Transporte', 'Lazer', 'Outros'] },
                pagination: { page: 1, per_page: 100, total: txs.length, total_pages: 1 }
            };
        }
        
        if (method === 'POST') {
            const allTxs = db.get('transactions');
            if (body.id) {
                const idx = allTxs.findIndex(t => t.id == body.id && t.user_id === uid);
                if (idx > -1) allTxs[idx] = { ...allTxs[idx], ...body };
            } else {
                body.id = Date.now();
                body.user_id = uid;
                allTxs.push(body);
            }
            db.set('transactions', allTxs);
            return { success: true, message: 'Salvo com sucesso!' };
        }
        
        if (method === 'DELETE') {
            const idToDelete = searchParams.get('id') || (url.includes('?id=') ? new URLSearchParams(url.split('?')[1]).get('id') : null);
            if (idToDelete) {
                db.set('transactions', db.get('transactions').filter(t => t.id != idToDelete));
                return { success: true, message: 'Excluído com sucesso!' };
            }
        }
    }

    if (route === 'alerts') {
        let txs = db.get('transactions').filter(t => t.user_id === uid);
        let overdue_count = 0;
        let overdue_total = 0;
        const today = new Date().toISOString().split('T')[0];
        
        txs.forEach(t => {
            if (t.status === 'Pendente' && t.date < today) {
                overdue_count++;
                overdue_total += parseFloat(t.amount);
            }
        });
        
        return { success: true, overdue_count, overdue_total };
    }

    if (route === 'links') {
        const id = searchParams.get('id');
        let links = db.get('links').filter(l => l.user_id === uid);
        
        if (method === 'GET') return { success: true, links };
        
        if (method === 'POST') {
            const allLinks = db.get('links');
            if (body.id) {
                const idx = allLinks.findIndex(l => l.id == body.id && l.user_id === uid);
                if (idx > -1) allLinks[idx] = { ...allLinks[idx], ...body };
            } else {
                body.id = Date.now();
                body.user_id = uid;
                allLinks.push(body);
            }
            db.set('links', allLinks);
            return { success: true, message: 'Link salvo!' };
        }
        
        if (method === 'DELETE') {
            const idToDelete = searchParams.get('id') || (url.includes('?id=') ? new URLSearchParams(url.split('?')[1]).get('id') : null);
            if (idToDelete) {
                db.set('links', db.get('links').filter(l => l.id != idToDelete));
                return { success: true };
            }
        }
    }
    
    if (route === 'profile') {
        if (method === 'PUT') {
            const users = db.get('users');
            const idx = users.findIndex(u => u.id === uid);
            if (idx > -1) {
                if (body.name) users[idx].name = body.name;
                if (body.email) users[idx].email = body.email;
                if (body.new_password) users[idx].password = body.new_password;
                db.set('users', users);
                app.currentUser.name = users[idx].name;
                localStorage.setItem('contasLGS_currentUser', JSON.stringify(app.currentUser));
                return { success: true, message: 'Perfil atualizado!' };
            }
        }
    }

    if (route === 'users') {
        if (method === 'GET') {
            return { success: true, users: db.get('users').map(u => {
                const { password, ...uWithoutPwd } = u;
                return uWithoutPwd;
            })};
        }
        if (method === 'POST' || method === 'PUT') {
            const users = db.get('users');
            if (body.id) {
                const idx = users.findIndex(u => u.id == body.id);
                if (idx > -1) users[idx] = { ...users[idx], ...body };
            } else {
                body.id = Date.now();
                users.push(body);
            }
            db.set('users', users);
            return { success: true, message: 'Usuário salvo!' };
        }
        if (method === 'DELETE') {
            const idToDelete = searchParams.get('id') || (url.includes('?id=') ? new URLSearchParams(url.split('?')[1]).get('id') : null);
            if (idToDelete) {
                db.set('users', db.get('users').filter(u => u.id != idToDelete));
                return { success: true, message: 'Excluído com sucesso!' };
            }
        }
    }
    
    if (route === 'import') {
        if (method === 'POST') {
            let txs = db.get('transactions');
            let importedCount = 0;
            if (body.rows && Array.isArray(body.rows)) {
                body.rows.forEach(row => {
                    row.id = Date.now() + Math.random();
                    row.user_id = uid;
                    txs.push(row);
                    importedCount++;
                });
                db.set('transactions', txs);
            }
            return { success: true, imported: importedCount, errors: [] };
        }
    }

    return { success: true };
}

// Intercepta forms de auth
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const un = document.getElementById('username').value;
            const pw = document.getElementById('password').value;
            const alertBox = document.getElementById('loginAlert');
            const alertText = document.getElementById('loginAlertText');
            
            const users = db.get('users');
            const user = users.find(u => u.username === un && u.password === pw);
            if (user) {
                localStorage.setItem('contasLGS_currentUser', JSON.stringify({ id: user.id, name: user.name, role: user.role }));
                window.location.href = 'index.html';
            } else {
                alertText.textContent = 'Usuário ou senha incorretos.';
                alertBox.style.display = 'flex';
            }
        });
    }

    const regForm = document.getElementById('registerForm');
    if (regForm) {
        regForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const nm   = document.getElementById('name').value.trim();
            const em   = document.getElementById('email').value.trim();
            const un   = document.getElementById('username').value.trim();
            const pw   = document.getElementById('password').value;
            const pwc  = document.getElementById('passwordConfirm').value;
            
            const alertErr  = document.getElementById('registerAlertError');
            const alertErrTxt  = document.getElementById('registerAlertErrorText');
            const alertSucc = document.getElementById('registerAlertSuccess');
            const alertSuccTxt = document.getElementById('registerAlertSuccessText');
            
            alertErr.style.display  = 'none';
            alertSucc.style.display = 'none';

            const showErr = (msg) => {
                alertErrTxt.textContent = msg;
                alertErr.style.display  = 'flex';
            };
            
            if (!nm)            return showErr('O nome completo é obrigatório.');
            if (!em)            return showErr('O e-mail é obrigatório.');
            if (!un)            return showErr('O nome de usuário é obrigatório.');
            if (pw.length < 6)  return showErr('A senha deve ter no mínimo 6 caracteres.');
            if (pw !== pwc)     return showErr('As senhas não coincidem.');
            
            const users = db.get('users');
            if (users.find(u => u.username === un)) {
                return showErr('Este nome de usuário já está sendo utilizado.');
            }
            if (users.find(u => u.email === em)) {
                return showErr('Este e-mail já está cadastrado.');
            }
            
            users.push({ id: Date.now(), name: nm, email: em, username: un, password: pw, role: 'user' });
            db.set('users', users);
            
            alertSuccTxt.textContent = 'Cadastro realizado com sucesso! Redirecionando...';
            alertSucc.style.display  = 'flex';
            setTimeout(() => window.location.href = 'login.html', 1500);
        });
    }
});
