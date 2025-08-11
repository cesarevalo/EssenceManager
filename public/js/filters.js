document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('filter-form');
    const productGrid = document.getElementById('product-grid');
    const noResultsDiv = document.getElementById('no-results');
    const priceRange = document.getElementById('price-range');
    const priceValue = document.getElementById('price-value');
    const sidebar = document.getElementById('filter-sidebar');
    const toggleButton = document.getElementById('filter-toggle-button');
    const closeButton = document.getElementById('close-filter-button');
    const overlay = document.getElementById('overlay');
    
    const searchInputDesktop = document.getElementById('search-input-desktop');
    const searchClearDesktop = document.getElementById('search-clear-desktop');
    const searchInputMobile = document.getElementById('search-input-mobile');
    const searchClearMobile = document.getElementById('search-clear-mobile');
    
    let debounceTimer;

    function fetchProducts() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        
        fetch(`api/filter_products.php?${params}`)
            .then(response => response.json())
            .then(data => {
                productGrid.innerHTML = '';
                if (data.length === 0) {
                    noResultsDiv.style.display = 'block';
                } else {
                    noResultsDiv.style.display = 'none';
                    data.forEach(product => {
                        const imageUrl = `uploads/products/${product.imagen_url || 'images/placeholder.png'}`;
                        const detailUrl = `product_detail.php?id=${product.id}`;
                        
                        let availabilityBadge = '';
                        if (product.stock <= 0) {
                            availabilityBadge = '<span class="badge bg-secondary position-absolute top-0 start-0 m-2" style="z-index: 1;">Próximamente</span>';
                        }
                        
                        const productCard = `
                            <div class="col">
                                <div class="card h-100 product-card">
                                    <div style="position: relative;">
                                        ${availabilityBadge}
                                        <img src="${imageUrl}" class="card-img-top" alt="${product.nombre}">
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">${product.marca_nombre}</h6>
                                        <h5 class="card-title">${product.nombre}</h5>
                                        <p class="card-text text-muted">${product.tamano_ml} ml</p>
                                        <p class="card-text price">${parseFloat(product.precio_usdt).toFixed(2)} USDT</p>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0">
                                         <a href="${detailUrl}" class="btn btn-dark w-100">Ver Detalles</a>
                                    </div>
                                </div>
                            </div>
                        `;
                        productGrid.innerHTML += productCard;
                    });
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Carga inicial
    fetchProducts();

    // Listener para los filtros de checkbox y rango
    document.querySelectorAll('.filter-change').forEach(item => {
        item.addEventListener('change', fetchProducts);
    });
    
    if (priceRange) {
        priceRange.addEventListener('input', function() {
            priceValue.textContent = `$${this.value}`;
        });
    }

    // Función para manejar la búsqueda desde cualquier input
    function handleSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchProducts();
        }, 500);
    }

    // Sincronización y listeners para los buscadores
    searchInputDesktop.addEventListener('keyup', () => {
        searchInputMobile.value = searchInputDesktop.value;
        handleSearch();
    });

    searchInputMobile.addEventListener('keyup', () => {
        searchInputDesktop.value = searchInputMobile.value;
        handleSearch();
    });
    
    // Listeners para los botones de limpiar
    searchClearDesktop.addEventListener('click', () => {
        searchInputDesktop.value = '';
        searchInputMobile.value = '';
        fetchProducts();
    });

    searchClearMobile.addEventListener('click', () => {
        searchInputDesktop.value = '';
        searchInputMobile.value = '';
        fetchProducts();
    });

    // Lógica para el sidebar
    function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
    if (toggleButton) { toggleButton.addEventListener('click', openSidebar); }
    if (closeButton) { closeButton.addEventListener('click', closeSidebar); }
    if (overlay) { overlay.addEventListener('click', closeSidebar); }
});