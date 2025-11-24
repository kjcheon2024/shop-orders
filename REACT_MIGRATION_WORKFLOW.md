# PHP → React 전환 워크플로우

## 📋 프로젝트 개요

현재 프로젝트는 PHP 기반의 주문 관리 시스템입니다. 이를 React 기반의 모던 웹 애플리케이션으로 전환하는 단계별 가이드입니다.

---

## 🏗️ 아키텍처 설계

### 현재 구조
```
PHP Backend (서버 사이드 렌더링)
├── index.php (사용자 페이지)
├── admin.php (관리자 페이지)
├── functions.php (비즈니스 로직)
├── config.php (설정)
└── assets/js/*.js (Vanilla JavaScript)
```

### 목표 구조
```
React Frontend (SPA)
├── src/
│   ├── components/ (재사용 컴포넌트)
│   ├── pages/ (페이지 컴포넌트)
│   ├── hooks/ (커스텀 훅)
│   ├── services/ (API 호출)
│   ├── context/ (상태 관리)
│   └── utils/ (유틸리티)
│
PHP Backend API (RESTful API)
├── api/
│   ├── auth.php (인증)
│   ├── orders.php (주문)
│   ├── companies.php (업체)
│   ├── items.php (품목)
│   └── admin.php (관리자)
└── functions.php (비즈니스 로직 - 유지)
```

---

## 📝 단계별 전환 워크플로우

### Phase 1: 프로젝트 설정 및 환경 구성

#### 1.1 React 프로젝트 초기화
```bash
# React 프로젝트 생성
npx create-react-app shop-orders-frontend
cd shop-orders-frontend

# 또는 Vite 사용 (더 빠름)
npm create vite@latest shop-orders-frontend -- --template react
cd shop-orders-frontend
npm install
```

#### 1.2 필수 패키지 설치
```bash
# 라우팅
npm install react-router-dom

# 상태 관리 (선택사항)
npm install zustand
# 또는
npm install @reduxjs/toolkit react-redux

# HTTP 클라이언트
npm install axios

# 폼 관리
npm install react-hook-form

# UI 라이브러리 (선택사항)
npm install @mui/material @emotion/react @emotion/styled
# 또는
npm install antd
```

#### 1.3 프로젝트 구조 생성
```
shop-orders-frontend/
├── public/
├── src/
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.jsx
│   │   │   ├── Input.jsx
│   │   │   ├── Modal.jsx
│   │   │   └── Alert.jsx
│   │   ├── layout/
│   │   │   ├── Header.jsx
│   │   │   ├── Navbar.jsx
│   │   │   └── Footer.jsx
│   │   └── forms/
│   │       ├── LoginForm.jsx
│   │       ├── RegistrationForm.jsx
│   │       └── OrderForm.jsx
│   ├── pages/
│   │   ├── user/
│   │   │   ├── LoginPage.jsx
│   │   │   ├── RegistrationPage.jsx
│   │   │   ├── OrderPage.jsx
│   │   │   ├── OrderStatusPage.jsx
│   │   │   └── ItemManagementPage.jsx
│   │   └── admin/
│   │       ├── AdminLoginPage.jsx
│   │       ├── OrdersPage.jsx
│   │       ├── CompaniesPage.jsx
│   │       ├── ItemsPage.jsx
│   │       └── SettingsPage.jsx
│   ├── services/
│   │   ├── api.js (axios 설정)
│   │   ├── authService.js
│   │   ├── orderService.js
│   │   ├── companyService.js
│   │   └── adminService.js
│   ├── context/
│   │   ├── AuthContext.jsx
│   │   └── OrderContext.jsx
│   ├── hooks/
│   │   ├── useAuth.js
│   │   ├── useOrders.js
│   │   └── useTimeRestriction.js
│   ├── utils/
│   │   ├── dateUtils.js
│   │   ├── validation.js
│   │   └── constants.js
│   ├── App.jsx
│   ├── App.css
│   └── index.js
└── package.json
```

---

### Phase 2: 백엔드 API 리팩토링

#### 2.1 API 엔드포인트 구조 설계

**기존 PHP 파일을 API로 변환:**

```
api/
├── auth.php
│   ├── POST /api/auth/login
│   ├── POST /api/auth/logout
│   └── GET /api/auth/check
│
├── orders.php
│   ├── POST /api/orders (주문 생성)
│   ├── GET /api/orders/today (오늘 주문 조회)
│   ├── GET /api/orders/history (주문 이력)
│   └── PUT /api/orders/:id (주문 수정)
│
├── companies.php
│   ├── POST /api/companies/register (업체 등록)
│   ├── GET /api/companies/me (내 정보)
│   └── PUT /api/companies/me (정보 수정)
│
├── items.php
│   ├── GET /api/items (품목 목록)
│   ├── GET /api/items/assigned (할당된 품목)
│   └── POST /api/items/request (품목 요청)
│
└── admin.php
    ├── GET /api/admin/orders (관리자 주문 조회)
    ├── POST /api/admin/companies/approve (업체 승인)
    ├── GET /api/admin/companies (업체 목록)
    └── ... (기타 관리자 기능)
```

#### 2.2 API 응답 형식 표준화

**성공 응답:**
```json
{
  "success": true,
  "data": { ... },
  "message": "성공 메시지"
}
```

**에러 응답:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "에러 메시지"
  }
}
```

#### 2.3 CORS 설정

`config.php` 또는 `.htaccess`에 추가:
```php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
```

---

### Phase 3: 프론트엔드 컴포넌트 개발

#### 3.1 공통 컴포넌트 개발

**Button.jsx**
```jsx
import './Button.css';

const Button = ({ children, onClick, variant = 'primary', disabled, ...props }) => {
  return (
    <button
      className={`btn btn-${variant}`}
      onClick={onClick}
      disabled={disabled}
      {...props}
    >
      {children}
    </button>
  );
};

export default Button;
```

**Input.jsx**
```jsx
const Input = ({ label, error, ...props }) => {
  return (
    <div className="form-group">
      {label && <label>{label}</label>}
      <input {...props} />
      {error && <span className="error-message">{error}</span>}
    </div>
  );
};

export default Input;
```

#### 3.2 서비스 레이어 개발

**services/api.js**
```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost/shop-orders',
  withCredentials: true, // 세션 쿠키 전송
  headers: {
    'Content-Type': 'application/json',
  },
});

// 요청 인터셉터
api.interceptors.request.use(
  (config) => {
    // 토큰이 있다면 추가
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// 응답 인터셉터
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // 인증 실패 시 로그인 페이지로
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

**services/authService.js**
```javascript
import api from './api';

export const authService = {
  login: async (password) => {
    return api.post('/api/auth/login', { password });
  },

  logout: async () => {
    return api.post('/api/auth/logout');
  },

  checkAuth: async () => {
    return api.get('/api/auth/check');
  },
};
```

**services/orderService.js**
```javascript
import api from './api';

export const orderService = {
  createOrder: async (orderData) => {
    return api.post('/api/orders', orderData);
  },

  getTodayOrder: async (companyName) => {
    return api.get('/api/orders/today', { params: { companyName } });
  },

  getOrderHistory: async (companyName, days = 7) => {
    return api.get('/api/orders/history', { 
      params: { companyName, days } 
    });
  },

  updateOrder: async (orderId, orderData) => {
    return api.put(`/api/orders/${orderId}`, orderData);
  },
};
```

#### 3.3 Context API로 상태 관리

**context/AuthContext.jsx**
```jsx
import React, { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../services/authService';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const response = await authService.checkAuth();
      if (response.success) {
        setUser(response.data);
      }
    } catch (error) {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  const login = async (password) => {
    const response = await authService.login(password);
    if (response.success) {
      setUser(response.data);
      return response;
    }
    throw new Error(response.error?.message);
  };

  const logout = async () => {
    await authService.logout();
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};
```

#### 3.4 페이지 컴포넌트 개발

**pages/user/LoginPage.jsx**
```jsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import Input from '../../components/common/Input';
import Button from '../../components/common/Button';

const LoginPage = () => {
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await login(password);
      if (response.success) {
        navigate('/orders');
      }
    } catch (err) {
      setError(err.message || '로그인에 실패했습니다.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      <div className="container">
        <h1>천하유통</h1>
        <p>비밀번호 입력시 업체명 자동매칭</p>
        <form onSubmit={handleSubmit}>
          <Input
            type="password"
            placeholder="비밀번호"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          {error && <div className="error-message">{error}</div>}
          <Button type="submit" disabled={loading || !password}>
            {loading ? '로그인 중...' : '로그인'}
          </Button>
        </form>
      </div>
    </div>
  );
};

export default LoginPage;
```

**pages/user/OrderPage.jsx**
```jsx
import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { orderService } from '../../services/orderService';
import { useTimeRestriction } from '../../hooks/useTimeRestriction';

const OrderPage = () => {
  const { user } = useAuth();
  const { isOrderTimeAllowed } = useTimeRestriction();
  const [items, setItems] = useState([]);
  const [orderData, setOrderData] = useState({});
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadItems();
  }, []);

  const loadItems = async () => {
    // 품목 목록 로드
  };

  const handleOrder = async () => {
    if (!isOrderTimeAllowed()) {
      alert('주문 가능 시간이 아닙니다. (08:00 ~ 익일 05:00)');
      return;
    }

    setLoading(true);
    try {
      const response = await orderService.createOrder({
        companyName: user.company_name,
        orders: orderData,
      });
      if (response.success) {
        alert('주문이 완료되었습니다.');
        // 주문 조회 페이지로 이동
      }
    } catch (error) {
      alert(error.message || '주문 처리 중 오류가 발생했습니다.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="order-page">
      {/* 주문 폼 UI */}
    </div>
  );
};

export default OrderPage;
```

#### 3.5 커스텀 훅 개발

**hooks/useTimeRestriction.js**
```javascript
import { useState, useEffect } from 'react';

export const useTimeRestriction = () => {
  const [isAllowed, setIsAllowed] = useState(true);
  const [nextOrderTime, setNextOrderTime] = useState(null);

  useEffect(() => {
    const checkTime = () => {
      const now = new Date();
      const currentHour = now.getHours();
      const allowed = !(currentHour >= 5 && currentHour < 8);
      setIsAllowed(allowed);

      if (!allowed) {
        // 다음 주문 가능 시간 계산
        const nextTime = new Date(now);
        nextTime.setHours(8, 0, 0, 0);
        setNextOrderTime(nextTime);
      }
    };

    checkTime();
    const interval = setInterval(checkTime, 60000); // 1분마다 체크

    return () => clearInterval(interval);
  }, []);

  return {
    isOrderTimeAllowed: () => isAllowed,
    nextOrderTime,
  };
};
```

---

### Phase 4: 라우팅 설정

**App.jsx**
```jsx
import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import LoginPage from './pages/user/LoginPage';
import RegistrationPage from './pages/user/RegistrationPage';
import OrderPage from './pages/user/OrderPage';
import OrderStatusPage from './pages/user/OrderStatusPage';
import ItemManagementPage from './pages/user/ItemManagementPage';
import AdminLoginPage from './pages/admin/AdminLoginPage';
import AdminOrdersPage from './pages/admin/AdminOrdersPage';
import PrivateRoute from './components/common/PrivateRoute';

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* 사용자 라우트 */}
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegistrationPage />} />
          <Route
            path="/orders"
            element={
              <PrivateRoute>
                <OrderPage />
              </PrivateRoute>
            }
          />
          <Route
            path="/order-status"
            element={
              <PrivateRoute>
                <OrderStatusPage />
              </PrivateRoute>
            }
          />
          <Route
            path="/items"
            element={
              <PrivateRoute>
                <ItemManagementPage />
              </PrivateRoute>
            }
          />

          {/* 관리자 라우트 */}
          <Route path="/admin/login" element={<AdminLoginPage />} />
          <Route
            path="/admin/orders"
            element={
              <PrivateRoute admin>
                <AdminOrdersPage />
              </PrivateRoute>
            }
          />

          {/* 기본 리다이렉트 */}
          <Route path="/" element={<Navigate to="/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
```

**components/common/PrivateRoute.jsx**
```jsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

const PrivateRoute = ({ children, admin = false }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return <div>로딩 중...</div>;
  }

  if (!user) {
    return <Navigate to={admin ? '/admin/login' : '/login'} replace />;
  }

  if (admin && !user.isAdmin) {
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default PrivateRoute;
```

---

### Phase 5: 스타일링 마이그레이션

#### 5.1 CSS 모듈 또는 Styled Components 사용

**옵션 1: CSS Modules**
```jsx
// OrderPage.module.css
.orderPage {
  padding: 20px;
}

// OrderPage.jsx
import styles from './OrderPage.module.css';

<div className={styles.orderPage}>
```

**옵션 2: Styled Components**
```bash
npm install styled-components
```

```jsx
import styled from 'styled-components';

const OrderPageContainer = styled.div`
  padding: 20px;
`;
```

#### 5.2 기존 CSS 파일 변환

기존 `assets/css/*.css` 파일을 컴포넌트별로 분리하거나, 전역 스타일로 유지

---

### Phase 6: 점진적 마이그레이션 전략

#### 6.1 하이브리드 접근법 (권장)

1. **기존 PHP 페이지 유지**
   - 기존 시스템은 그대로 운영

2. **새 기능은 React로 개발**
   - 새로운 기능이나 개선사항은 React로 개발

3. **점진적 전환**
   - 페이지별로 React로 전환
   - 예: 주문 페이지 → React, 관리자 페이지는 나중에

#### 6.2 마이크로프론트엔드 접근법

- React 앱을 별도 서브도메인으로 배포
- PHP 페이지에서 iframe 또는 Web Components로 통합

---

### Phase 7: 테스트 및 배포

#### 7.1 테스트 전략

```bash
# 테스트 라이브러리 설치
npm install --save-dev @testing-library/react @testing-library/jest-dom
```

**예시 테스트:**
```javascript
// LoginPage.test.jsx
import { render, screen, fireEvent } from '@testing-library/react';
import LoginPage from './LoginPage';

test('로그인 폼 제출', async () => {
  render(<LoginPage />);
  const passwordInput = screen.getByPlaceholderText('비밀번호');
  fireEvent.change(passwordInput, { target: { value: 'test123' } });
  // ...
});
```

#### 7.2 빌드 및 배포

```bash
# 프로덕션 빌드
npm run build

# 빌드된 파일을 서버에 배포
# build/ 폴더의 내용을 웹 서버에 업로드
```

**배포 구조:**
```
서버 루트/
├── api/ (PHP API)
├── build/ (React 빌드 파일)
└── .htaccess (리라이트 규칙)
```

**.htaccess 설정:**
```apache
# React Router를 위한 리라이트
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

---

## 🔧 주요 고려사항

### 1. 세션 관리
- PHP 세션을 JWT 토큰으로 전환 고려
- 또는 세션 쿠키를 그대로 사용 (withCredentials: true)

### 2. 파일 업로드
- FormData를 사용한 파일 업로드 처리
- 진행률 표시를 위한 axios 인터셉터 활용

### 3. Google Sheets API
- PHP 백엔드에서만 처리 (보안상 서버 사이드에서만)
- React는 API를 통해 결과만 받음

### 4. 실시간 업데이트
- 필요시 WebSocket 또는 Server-Sent Events (SSE) 고려
- 또는 폴링 방식 유지

### 5. 성능 최적화
- 코드 스플리팅
- React.lazy()를 사용한 지연 로딩
- 이미지 최적화

---

## 📅 예상 일정 (참고)

- **Phase 1-2**: 1-2주 (설정 및 API 리팩토링)
- **Phase 3**: 3-4주 (컴포넌트 개발)
- **Phase 4-5**: 1주 (라우팅 및 스타일링)
- **Phase 6**: 2-3주 (점진적 마이그레이션)
- **Phase 7**: 1주 (테스트 및 배포)

**총 예상 기간: 8-11주**

---

## 📚 참고 자료

- [React 공식 문서](https://react.dev/)
- [React Router](https://reactrouter.com/)
- [Axios 문서](https://axios-http.com/)
- [React Hook Form](https://react-hook-form.com/)

---

## ✅ 체크리스트

### Phase 1
- [ ] React 프로젝트 초기화
- [ ] 필수 패키지 설치
- [ ] 프로젝트 구조 생성

### Phase 2
- [ ] API 엔드포인트 설계
- [ ] PHP API 파일 생성
- [ ] CORS 설정

### Phase 3
- [ ] 공통 컴포넌트 개발
- [ ] 서비스 레이어 개발
- [ ] Context API 설정
- [ ] 페이지 컴포넌트 개발
- [ ] 커스텀 훅 개발

### Phase 4
- [ ] 라우팅 설정
- [ ] 인증 가드 구현

### Phase 5
- [ ] 스타일 마이그레이션
- [ ] 반응형 디자인 적용

### Phase 6
- [ ] 점진적 마이그레이션 계획 수립
- [ ] 테스트 환경 구축

### Phase 7
- [ ] 단위 테스트 작성
- [ ] 통합 테스트
- [ ] 프로덕션 빌드
- [ ] 배포 및 모니터링

