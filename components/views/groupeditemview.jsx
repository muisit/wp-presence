import React from 'react';
import { Button } from 'primereact/button';
import { Calendar } from 'primereact/calendar';
import GroupView from './groupview';
import GenericDialog from '../dialogs/genericdialog';

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
        var attrs = this.props.template.attributes.map((item) => {
            var def = item.value;
            if (item.type === 'enum') {
                def = item.value.split(' ')[0];
            }
            else if (item.type == 'int') {
                def = 0;
            }
            else if (item.type == 'year') {
                def = 2000;
            }
            else if (item.type == 'number') {
                def = 0.0;
            }
            return {
                id: -1,
                name: item.name,
                type: item.type,
                value: def
            }
        });
        console.log('adding new item with attributes ',attrs)
        this.setState({ item: { id: -1, name: '', type: this.props.template.name, state: 'new', attributes: attrs }, displayDialog: true });
    }

    render() {
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
                            <Calendar className='fullwidth' appendTo={document.body} onChange={this.onChangeDate} dateFormat="yy-mm-dd" value={new Date(this.props.date)}></Calendar><br />
                            <GroupView showElement={this.props.onShowElement} date={this.props.date} template={this.props.template} group={this.props.group} byId={this.props.byId} idx='' onChange={this.props.onChangePresence} />
                            <Button icon="pi pi-plus" className="floataction p-button-rounded p-button-help" onClick={this.showEntryDialog} />
                            <GenericDialog width='100vw' onClose={this.onClose} onChange={this.onChange} onSave={this.onSave} onlyAdd={true} display={this.state.displayDialog} value={this.state.item} template={this.props.template} />
                        </div>
                    </div>
                </div>
            );
    }
}
