# Step 6: The Tax Engine (Category & Value Object)

## Purpose
Taxes in the Mexican fiscal system don't just have amounts; they have behaviors. A tax is either transferred (`Trasladado`) or retained (`Retenido`). The Tax Engine encapsulates this behavior to prevent mathematical leaks.

## Implementation Details
- **`TaxCategory` Enum**: Holds the domain knowledge of whether a tax is deducted from or added to the total. It uses the "Information Expert" principle (e.g., `isDeductedFromTotal()`) to eliminate repetitive `if ($tax === 'retained')` checks across the system.
- **`Tax` Value Object**: Encapsulates the specific tax line item (name, category, amount, rate).
- **"Tell, Don't Ask"**: The `applyToTotal()` method inside the `Tax` object asks the `TaxCategory` for the direction, and asks the `Money` object to do the math. The `Tax` object simply orchestrates.

## Impact on Architecture
This structure completely prevents a developer from accidentally adding an ISR Retention or subtracting an IVA Transfer. The direction of the math is physically bound to the category of the tax.
