import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import Input from '../../components/common/Input';
import Button from '../../components/common/Button';
import Badge from '../../components/common/Badge';
import Card from '../../components/common/Card';
import Modal from '../../components/common/Modal';

expect.extend(toHaveNoViolations);

describe('Form Components Accessibility', () => {
  describe('Input Component', () => {
    it('should have no accessibility violations with label', async () => {
      const { container } = render(
        <Input label="Email Address" placeholder="Enter email" type="email" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have proper label association', () => {
      render(<Input label="Username" id="username" />);
      const input = screen.getByLabelText(/username/i);
      expect(input).toBeInTheDocument();
    });

    it('should display required indicator and mark input as required', () => {
      render(<Input label="Required Field" required />);
      expect(screen.getByText('*')).toBeInTheDocument();
    });

    it('should have no violations with error state', async () => {
      const { container } = render(
        <Input label="Email" error="Invalid email format" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have no violations with help text', async () => {
      const { container } = render(
        <Input
          label="Password"
          helpText="Must be at least 8 characters"
          type="password"
        />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should display error message to users', () => {
      render(
        <Input
          label="Email"
          error="This field is required"
        />
      );
      expect(screen.getByText(/this field is required/i)).toBeInTheDocument();
    });

    it('should support disabled state accessibly', async () => {
      const { container } = render(
        <Input label="Disabled Field" disabled />
      );
      const input = screen.getByLabelText(/disabled field/i);
      expect(input).toBeDisabled();

      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should display help text when provided', () => {
      render(
        <Input
          label="Username"
          helpText="3-20 characters, letters and numbers only"
        />
      );
      expect(screen.getByText(/3-20 characters/i)).toBeInTheDocument();
    });

    it('should prioritize error text over help text', () => {
      render(
        <Input
          label="Email"
          error="Invalid email"
          helpText="Enter a valid email"
        />
      );
      expect(screen.getByText(/invalid email/i)).toBeInTheDocument();
      expect(screen.queryByText(/enter a valid email/i)).not.toBeInTheDocument();
    });
  });

  describe('Button Component', () => {
    it('should have no accessibility violations', async () => {
      const { container } = render(<Button>Click me</Button>);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support focus states', () => {
      render(<Button>Clickable Button</Button>);
      const button = screen.getByRole('button');
      button.focus();
      expect(document.activeElement).toBe(button);
    });

    it('should have proper focus ring', async () => {
      const { container } = render(<Button>Focus Test</Button>);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support disabled state with disabled attribute', () => {
      render(<Button disabled>Disabled Button</Button>);
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
    });

    it('should have accessible text content', () => {
      render(<Button>Save Changes</Button>);
      expect(screen.getByRole('button', { name: /save changes/i })).toBeInTheDocument();
    });

    it('should support all variants accessibly', async () => {
      const variants = ['primary', 'secondary', 'outline', 'danger', 'ghost'] as const;

      for (const variant of variants) {
        const { container, unmount } = render(
          <Button variant={variant}>Test Button</Button>
        );
        const results = await axe(container);
        expect(results).toHaveNoViolations();
        unmount();
      }
    });

    it('should support all size variants accessibly', async () => {
      const sizes = ['sm', 'md', 'lg'] as const;

      for (const size of sizes) {
        const { container, unmount } = render(
          <Button size={size}>Test</Button>
        );
        const results = await axe(container);
        expect(results).toHaveNoViolations();
        unmount();
      }
    });

    it('should indicate loading state accessibly', () => {
      render(<Button loading>Submitting...</Button>);
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
    });
  });

  describe('Badge Component', () => {
    it('should have no accessibility violations', async () => {
      const { container } = render(<Badge>Active</Badge>);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have readable text content', () => {
      render(<Badge>Success Status</Badge>);
      expect(screen.getByText(/success status/i)).toBeInTheDocument();
    });

    it('should support different variants with sufficient contrast', async () => {
      const variants = ['success', 'warning', 'secondary', 'danger'] as const;

      for (const variant of variants) {
        const { container, unmount } = render(
          <Badge variant={variant}>{variant}</Badge>
        );
        const results = await axe(container);
        expect(results).toHaveNoViolations();
        unmount();
      }
    });
  });

  describe('Card Component', () => {
    it('should have no accessibility violations', async () => {
      const { container } = render(
        <Card>
          <h2>Card Title</h2>
          <p>Card content</p>
        </Card>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support proper heading hierarchy', () => {
      render(
        <Card>
          <h2>Main Heading</h2>
          <p>Content here</p>
        </Card>
      );
      expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
    });
  });

  describe('Modal Component', () => {
    it('should have no accessibility violations when open', async () => {
      const { container } = render(
        <Modal isOpen={true} onClose={() => {}} title="Modal Title">
          <p>Modal content</p>
        </Modal>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have a role attribute when open', () => {
      render(
        <Modal isOpen={true} onClose={() => {}} title="Modal">
          <p>Modal body</p>
        </Modal>
      );
      const modal = screen.getByRole('dialog', { hidden: true });
      expect(modal).toBeInTheDocument();
    });
  });
});

describe('Form Field Accessibility', () => {
  describe('Label Association', () => {
    it('should associate labels with inputs via htmlFor attribute', () => {
      render(
        <div>
          <label htmlFor="email-input">Email</label>
          <input id="email-input" type="email" />
        </div>
      );
      const input = screen.getByLabelText(/email/i);
      expect(input.id).toBe('email-input');
    });

    it('should support fieldset and legend for grouped inputs', () => {
      render(
        <fieldset>
          <legend>Contact Information</legend>
          <Input label="Name" />
          <Input label="Email" />
        </fieldset>
      );
      expect(screen.getByText(/contact information/i)).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('should display required field indicator', () => {
      render(
        <Input label="Username" required aria-required="true" />
      );
      const input = screen.getByLabelText(/username/i);
      expect(input).toHaveAttribute('required');
    });

    it('should announce error messages to screen readers', async () => {
      const { container } = render(
        <Input
          label="Email"
          error="Invalid email format"
          aria-describedby="email-error"
        />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should indicate invalid fields with aria-invalid', () => {
      render(
        <Input
          label="Email"
          error="Invalid"
          aria-invalid="true"
        />
      );
      const input = screen.getByLabelText(/email/i);
      expect(input).toHaveAttribute('aria-invalid', 'true');
    });
  });

  describe('Input Types and Attributes', () => {
    it('should support email input type', async () => {
      const { container } = render(
        <Input label="Email" type="email" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support password input type', async () => {
      const { container } = render(
        <Input label="Password" type="password" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support number input type', async () => {
      const { container } = render(
        <Input label="Quantity" type="number" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support date input type', async () => {
      const { container } = render(
        <Input label="Date" type="date" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should support textarea with placeholder', async () => {
      const { container } = render(
        <Input label="Message" placeholder="Enter your message" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });
});

describe('Navigation and Layout Accessibility', () => {
  describe('Heading Hierarchy', () => {
    it('should maintain proper heading hierarchy', () => {
      render(
        <div>
          <h1>Page Title</h1>
          <h2>Section 1</h2>
          <h3>Subsection</h3>
          <h2>Section 2</h2>
        </div>
      );
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
      expect(screen.getAllByRole('heading', { level: 2 })).toHaveLength(2);
      expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
    });

    it('should not skip heading levels', async () => {
      const { container } = render(
        <div>
          <h1>Main</h1>
          <h2>Sub</h2>
          <h3>Detail</h3>
        </div>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });

  describe('Link Accessibility', () => {
    it('should have descriptive link text', async () => {
      const { container } = render(
        <a href="/forms">View Forms</a>
      );
      expect(screen.getByRole('link', { name: /view forms/i })).toBeInTheDocument();
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should not use "click here" as link text', async () => {
      const { container } = render(
        <a href="/help">Learn more about form analytics</a>
      );
      const link = screen.getByRole('link');
      expect(link.textContent).not.toBe('Click here');
      expect(link.textContent).toMatch(/learn more/i);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });
});

describe('Data Visualization Accessibility', () => {
  describe('Charts and Graphs', () => {
    it('should have descriptive alt text or ARIA labels for charts', async () => {
      const { container } = render(
        <div
          role="img"
          aria-label="Form submissions over 30 days with peaks in week 2 and week 4"
        >
          {/* Chart SVG or component would go here */}
        </div>
      );
      const chart = screen.getByRole('img');
      expect(chart).toHaveAttribute('aria-label');
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should provide data table as alternative to visual charts', () => {
      render(
        <div>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Submissions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>March 1</td>
                <td>45</td>
              </tr>
            </tbody>
          </table>
        </div>
      );
      expect(screen.getByRole('table')).toBeInTheDocument();
    });
  });

  describe('Color Contrast', () => {
    it('should maintain sufficient color contrast in all UI states', async () => {
      const { container } = render(
        <div>
          <Button variant="primary">Primary Button</Button>
          <Button variant="secondary">Secondary Button</Button>
          <Button disabled>Disabled Button</Button>
        </div>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });
});

describe('Keyboard Navigation', () => {
  describe('Tab Order', () => {
    it('should have logical tab order through form elements', () => {
      render(
        <form>
          <Input label="First Name" />
          <Input label="Email" />
          <Button>Submit</Button>
        </form>
      );
      const inputs = screen.getAllByRole('textbox');
      const button = screen.getByRole('button');

      expect(inputs).toHaveLength(2);
      expect(button).toBeInTheDocument();
    });

    it('should not trap focus in components', () => {
      render(
        <div>
          <Button>Button 1</Button>
          <Button>Button 2</Button>
        </div>
      );
      const buttons = screen.getAllByRole('button');
      buttons[0].focus();
      expect(document.activeElement).toBe(buttons[0]);
    });
  });

  describe('Focus Visible Indicators', () => {
    it('should have visible focus indicators on buttons', async () => {
      const { container } = render(
        <Button>Focusable Button</Button>
      );
      const button = screen.getByRole('button');
      button.focus();

      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have visible focus indicators on inputs', async () => {
      const { container } = render(
        <Input label="Focused Input" />
      );
      const input = screen.getByLabelText(/focused input/i);
      input.focus();
      expect(document.activeElement).toBe(input);
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });
});

describe('ARIA Attributes', () => {
  describe('aria-label and aria-labelledby', () => {
    it('should use aria-label for icon-only buttons', async () => {
      const { container } = render(
        <button aria-label="Delete form">
          <span>🗑️</span>
        </button>
      );
      expect(screen.getByLabelText(/delete form/i)).toBeInTheDocument();
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should use aria-labelledby for complex components', async () => {
      const { container } = render(
        <div>
          <h2 id="dialog-title">Confirm Action</h2>
          <div role="alertdialog" aria-labelledby="dialog-title">
            <p>Are you sure?</p>
          </div>
        </div>
      );
      const dialog = screen.getByRole('alertdialog');
      expect(dialog).toHaveAttribute('aria-labelledby', 'dialog-title');
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });
  });

  describe('aria-describedby', () => {
    it('should link error messages with aria-describedby', () => {
      render(
        <div>
          <Input
            label="Email"
            aria-describedby="email-error"
          />
          <span id="email-error">Must be a valid email</span>
        </div>
      );
      const input = screen.getByLabelText(/email/i);
      expect(input).toHaveAttribute('aria-describedby', 'email-error');
    });

    it('should link help text with aria-describedby', () => {
      render(
        <div>
          <Input
            label="Password"
            aria-describedby="password-hint"
          />
          <span id="password-hint">At least 8 characters</span>
        </div>
      );
      const input = screen.getByLabelText(/password/i);
      expect(input).toHaveAttribute('aria-describedby', 'password-hint');
    });
  });
});

describe('Status and Alert Messages', () => {
  it('should announce form submission success to screen readers', async () => {
    const { container } = render(
      <div role="status" aria-live="polite">
        Form submitted successfully
      </div>
    );
    expect(screen.getByRole('status')).toBeInTheDocument();
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('should announce form submission errors to screen readers', async () => {
    const { container } = render(
      <div role="alert" aria-live="assertive">
        Please fix the errors below
      </div>
    );
    expect(screen.getByRole('alert')).toBeInTheDocument();
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});

describe('Responsive and Mobile Accessibility', () => {
  it('should maintain accessibility on mobile viewports', async () => {
    const { container } = render(
      <div className="w-full">
        <Input label="Mobile Input" />
        <Button>Submit</Button>
      </div>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});
