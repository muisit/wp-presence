import React from 'react';
import { Button } from 'primereact/button';
import { Calendar } from 'primereact/calendar';
import GroupView from './groupview';
import GenericDialog from '../dialogs/genericdialog';
import { format_date, create_attributes_from_template } from '../functions';
import moment from 'moment';

export default class GroupedItemView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            item: null,
            displayDialog: false
        };
        this.abortType = 'frontend';
    }

    onChange = (item) => {
        this.setState({ item: item });
    }

    onSave = (item) => {
        // reload the parent item list
        this.props.onNewElement(item);
    }

    onClose = () => {
        this.setState({ displayDialog: false });
    }

    showEntryDialog = () => {
        var attrs = create_attributes_from_template(this.props.template.attributes);
        var dt={};
        attrs.map((a) => {
            dt[a.name]=a.value;
        });
        var newitem={
            id:-1,
            name: '',
            original: {
                id: -1,
                name: '',
                type:this.props.template.name,
                state: 'new',
                attributes: attrs
            },
            data: dt
        };
        this.props.onShowElement(newitem);
        //this.setState({ item: { id: -1, name: '', type: this.props.template.name, state: 'new', attributes: attrs }, displayDialog: true });
    }

    render() {
        var date=moment(this.props.date).format("YYYY-MM-DD");
        return (
                <div className='container'>
                    <div className='row'>
                        <div className='col-6 offset-md-3 col-md-3'>
                            <Button label="Terug" className="p-button-raised p-button-text fullwidth" onClick={this.props.onBack} />
                        </div>
                        <div className='col-6 col-md-3'>
                            <Button label="Lijst" className="p-button-raised p-button-text fullwidth" onClick={this.props.onList} />
                        </div>
                    </div>
                    <div className='row'>
                        <div className='col-12 offset-md-3 col-md-6'>
                            Date is {date}<br/>
                            <Calendar className='fullwidth' appendTo={document.body} onChange={this.props.onChangeDate} dateFormat="yy-mm-dd" value={new Date(date)}></Calendar><br />
                            <GroupView showElement={this.props.onShowElement} date={this.props.date} template={this.props.template} group={this.props.group} byId={this.props.byId} idx='' onChange={this.props.onChangePresence} />
                            <Button icon="pi pi-plus" className="floataction p-button-rounded p-button-help" onClick={this.showEntryDialog} />
                            <GenericDialog width='100vw' onClose={this.onClose} onChange={this.onChange} onSave={this.onSave} onlyAdd={true} display={this.state.displayDialog} value={this.state.item} template={this.props.template} />
                        </div>
                    </div>
                </div>
            );
    }
}
