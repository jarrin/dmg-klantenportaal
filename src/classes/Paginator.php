<?php
/**
 * Paginator Helper Class
 * Handles pagination logic and rendering
 */
class Paginator {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $totalPages;
    private $offset;
    
    /**
     * Initialize paginator
     * @param int $totalItems Total number of items
     * @param int $itemsPerPage Items to show per page (default: 10)
     * @param int $currentPage Current page number (default: 1)
     */
    public function __construct($totalItems, $itemsPerPage = 10, $currentPage = 1) {
        $this->totalItems = max(0, (int)$totalItems);
        $this->itemsPerPage = max(1, (int)$itemsPerPage);
        $this->currentPage = max(1, (int)$currentPage);
        $this->totalPages = max(1, (int)ceil($this->totalItems / $this->itemsPerPage));
        
        // Ensure current page doesn't exceed total pages
        if ($this->currentPage > $this->totalPages) {
            $this->currentPage = $this->totalPages;
        }
        
        $this->offset = ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Get SQL LIMIT clause
     * @return string SQL LIMIT clause
     */
    public function getLimitClause() {
        return "LIMIT {$this->itemsPerPage} OFFSET {$this->offset}";
    }
    
    /**
     * Get current page offset
     * @return int
     */
    public function getOffset() {
        return $this->offset;
    }
    
    /**
     * Get items per page
     * @return int
     */
    public function getItemsPerPage() {
        return $this->itemsPerPage;
    }
    
    /**
     * Get current page number
     * @return int
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    /**
     * Get total pages
     * @return int
     */
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    /**
     * Get total items
     * @return int
     */
    public function getTotalItems() {
        return $this->totalItems;
    }
    
    /**
     * Check if there is a previous page
     * @return bool
     */
    public function hasPreviousPage() {
        return $this->currentPage > 1;
    }
    
    /**
     * Check if there is a next page
     * @return bool
     */
    public function hasNextPage() {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Get page range info text
     * @return string E.g., "Showing 1-10 of 50"
     */
    public function getPageInfo() {
        if ($this->totalItems == 0) {
            return "Geen resultaten";
        }
        
        $start = $this->offset + 1;
        $end = min($this->offset + $this->itemsPerPage, $this->totalItems);
        
        return "Weergave {$start}-{$end} van {$this->totalItems}";
    }
    
    /**
     * Generate page numbers to display
     * @param int $adjacentPages Number of pages to show on each side of current page
     * @return array Array of page numbers and special markers
     */
    public function getPageNumbers($adjacentPages = 2) {
        $pages = [];
        
        if ($this->totalPages <= 1) {
            return $pages;
        }
        
        // Always show first page
        $pages[] = 1;
        
        // Calculate range around current page
        $rangeStart = max(2, $this->currentPage - $adjacentPages);
        $rangeEnd = min($this->totalPages - 1, $this->currentPage + $adjacentPages);
        
        // Add dots if there's a gap after first page
        if ($rangeStart > 2) {
            $pages[] = '...';
        }
        
        // Add pages in range
        for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
            $pages[] = $i;
        }
        
        // Add dots if there's a gap before last page
        if ($rangeEnd < $this->totalPages - 1) {
            $pages[] = '...';
        }
        
        // Always show last page (if more than 1 page)
        if ($this->totalPages > 1) {
            $pages[] = $this->totalPages;
        }
        
        return $pages;
    }
    
    /**
     * Render pagination HTML
     * @param string $baseUrl Base URL for pagination links (without page parameter)
     * @param array $additionalParams Additional GET parameters to preserve
     * @param string $pageParamName Name of the page parameter (default: 'page')
     * @return string HTML for pagination
     */
    public function render($baseUrl = '', $additionalParams = [], $pageParamName = 'page') {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        // Parse existing URL parameters
        $urlParts = parse_url($baseUrl);
        $queryParams = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }
        
        // Merge with additional params
        $queryParams = array_merge($queryParams, $additionalParams);
        
        // Build base URL without query string
        $cleanUrl = $urlParts['path'] ?? '';
        
        // Helper function to build URL with page param
        $buildUrl = function($page) use ($cleanUrl, $queryParams, $pageParamName) {
            $params = array_merge($queryParams, [$pageParamName => $page]);
            return $cleanUrl . '?' . http_build_query($params);
        };
        
        $html = '<div class="pagination-wrapper">';
        $html .= '<div class="pagination-info">' . htmlspecialchars($this->getPageInfo()) . '</div>';
        $html .= '<ul class="pagination">';
        
        // Previous button
        if ($this->hasPreviousPage()) {
            $html .= '<li class="prev"><a href="' . htmlspecialchars($buildUrl($this->currentPage - 1)) . '"><i class="fas fa-chevron-left"></i> Vorige</a></li>';
        } else {
            $html .= '<li class="prev disabled"><span><i class="fas fa-chevron-left"></i> Vorige</span></li>';
        }
        
        // Page numbers
        foreach ($this->getPageNumbers() as $page) {
            if ($page === '...') {
                $html .= '<li class="dots"><span>...</span></li>';
            } elseif ($page == $this->currentPage) {
                $html .= '<li class="active"><span>' . $page . '</span></li>';
            } else {
                $html .= '<li><a href="' . htmlspecialchars($buildUrl($page)) . '">' . $page . '</a></li>';
            }
        }
        
        // Next button
        if ($this->hasNextPage()) {
            $html .= '<li class="next"><a href="' . htmlspecialchars($buildUrl($this->currentPage + 1)) . '">Volgende <i class="fas fa-chevron-right"></i></a></li>';
        } else {
            $html .= '<li class="next disabled"><span>Volgende <i class="fas fa-chevron-right"></i></span></li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Create paginator from database query result
     * @param PDO $pdo PDO connection
     * @param string $countQuery SQL query to count total items
     * @param array $countParams Parameters for count query
     * @param int $itemsPerPage Items per page
     * @param int $currentPage Current page
     * @return Paginator
     */
    public static function fromQuery($pdo, $countQuery, $countParams = [], $itemsPerPage = 10, $currentPage = 1) {
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($countParams);
        $totalItems = $stmt->fetchColumn();
        
        return new self($totalItems, $itemsPerPage, $currentPage);
    }
}
